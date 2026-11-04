<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class Task
 */
final readonly class Task
{
    public const int MAX_VERSION = 2147483647;
    public const int MAX_TIME = 253402300799999;
    public const int MAX_STORED_BYTES = 40960;

    private const int REVISION_STRIDE = 32;

    private StrictJson $revision;

    /**
     * Constructs Task
     */
    private function __construct(
        private TaskDefinition $definition,
        private TaskState $state,
        private int $version,
        private int $createdAt,
        private int $updatedAt,
        private ?int $ttl,
        private ?int $pollInterval,
        private string $creationDigest,
        private ?string $lastDigest,
        private TaskInputs $inputs,
        private ?int $cancellationRequestedAt,
        private ?int $cancellationVersion,
        ?StrictJson $revision = null,
        ?self $previous = null
    ) {
        self::timestamp($createdAt);
        self::timestamp($updatedAt);
        self::interval($ttl);
        self::interval($pollInterval);
        if (
            $version < 1 || $version > self::MAX_VERSION || $updatedAt < $createdAt
            || ($ttl !== null && $createdAt > self::MAX_TIME - $ttl)
            || preg_match('/\A[0-9a-f]{64}\z/D', $creationDigest) !== 1
            || ($lastDigest !== null && preg_match('/\A[0-9a-f]{64}\z/D', $lastDigest) !== 1)
            || (($version === 1) !== ($lastDigest === null))
            || !$inputs->belongsTo($definition->scope(), $definition->id())
            || (($cancellationRequestedAt === null) !== ($cancellationVersion === null))
            || ($cancellationRequestedAt !== null && (
                $cancellationRequestedAt < $createdAt || $cancellationRequestedAt > $updatedAt
                || $cancellationVersion < 2 || $cancellationVersion > $version
            ))
        ) {
            throw new DomainException('Invalid retained Task metadata.');
        }

        if ($revision === null) {
            $counter = 1;
            $incarnation = bin2hex(random_bytes(16));
            if ($previous !== null) {
                $incarnation = $previous->revision->get('incarnation');
                $counter = (int) $previous->revision->get('counter');
                if ($this->changesPublicState($previous, $updatedAt)) {
                    ++$counter;
                }
            }

            $revision = StrictJson::fromObject(['incarnation' => $incarnation, 'counter' => $counter]);
        }

        if (
            count($revision->properties()) !== 2
            || !is_string($revision->get('incarnation'))
            || preg_match('/\A[0-9a-f]{32}\z/D', $revision->get('incarnation')) !== 1
            || !is_int($revision->get('counter')) || $revision->get('counter') < 1
            || $revision->get('counter') > self::MAX_VERSION
        ) {
            throw new DomainException('Invalid Task disclosure revision.');
        }

        $this->revision = $revision;
    }

    /**
     * Creates a working Task with immutable retention from actual creation time
     */
    public static function create(
        TaskDefinition $definition,
        TaskState $state,
        ?int $ttl,
        ?int $pollInterval,
        int $now
    ): self {
        if ($state->status() !== 'working') {
            throw new DomainException('A Task must start working.');
        }

        $digest = self::digest([$definition->toArray(), $state->toArray(), $ttl, $pollInterval]);

        return new self(
            $definition,
            $state,
            1,
            $now,
            $now,
            $ttl,
            $pollInterval,
            $digest,
            null,
            TaskInputs::empty($definition->scope(), $definition->id()),
            null,
            null
        );
    }

    /**
     * Creates a validated current snapshot without rerunning the original operation
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = [
            'schema', 'definition', 'state', 'version', 'created_at', 'updated_at', 'ttl', 'poll_interval',
            'creation_digest', 'last_digest', 'inputs', 'cancellation_requested_at', 'cancellation_version', 'revision'
        ];
        if (count($data) !== count($keys) || array_diff($keys, array_keys($data)) !== [] || $data['schema'] !== 1) {
            throw new DomainException('Unsupported Task representation.');
        }

        return new self(
            TaskDefinition::fromArray($data['definition']),
            TaskState::fromArray($data['state']),
            $data['version'],
            $data['created_at'],
            $data['updated_at'],
            $data['ttl'],
            $data['poll_interval'],
            $data['creation_digest'],
            $data['last_digest'],
            TaskInputs::fromData(StrictJson::fromData($data['inputs'])),
            $data['cancellation_requested_at'],
            $data['cancellation_version'],
            StrictJson::fromData($data['revision'])
        );
    }

    /**
     * Validates bounded epoch milliseconds independently of producer timestamps
     */
    public static function timestamp(int $value): int
    {
        if ($value < 0 || $value > self::MAX_TIME) {
            throw new DomainException('Invalid Task timestamp.');
        }

        return $value;
    }

    /**
     * Validates explicit positive millisecond advice or unlimited absent retention
     */
    public static function interval(?int $value): ?int
    {
        if ($value !== null && ($value < 1 || $value > 2147483647)) {
            throw new DomainException('Invalid Task millisecond interval.');
        }

        return $value;
    }

    /**
     * Returns whether this creation repeats the original retained content
     */
    public function repeatsCreation(self $candidate): bool
    {
        return $this->creationDigest === $candidate->creationDigest;
    }

    /**
     * Returns whether current state is logically retained without implying physical deletion
     */
    public function availableAt(int $now): bool
    {
        self::timestamp($now);

        return $this->ttl === null || $now < $this->createdAt + $this->ttl;
    }

    /**
     * Applies a version-fenced transition or recognizes only the immediately retained retry
     *
     * A null return is a matching retry, never renewed retention or a second state effect
     */
    public function transition(
        string $producer,
        int $expectedVersion,
        TaskState $next,
        ?int $pollInterval,
        int $now
    ): ?self {
        self::interval($pollInterval);
        self::timestamp($now);
        $digest = self::digest([$producer, $expectedVersion, $next->toArray(), $pollInterval]);
        if ($producer !== $this->definition->producer() || $expectedVersion < 1) {
            throw new DomainException('Task transition conflicts.');
        }

        if ($expectedVersion === $this->version - 1 && $this->lastDigest === $digest) {
            return null;
        }

        if ($expectedVersion !== $this->version || $this->state->isTerminal() || $this->version === self::MAX_VERSION) {
            throw new DomainException('Task transition conflicts.');
        }

        if (!$next->isTerminal()) {
            $outstanding = $this->inputs->outstanding($now);
            $input = $next->data()->get('input');
            if (
                ($outstanding->properties() !== []) !== ($next->status() === 'input_required')
                || ($input instanceof StrictJson && $input->toString() !== $outstanding->toString())
            ) {
                throw new DomainException('Producer state cannot replace retained input.');
            }
        }

        return new self(
            $this->definition,
            $next,
            $this->version + 1,
            $this->createdAt,
            max($this->updatedAt, $now),
            $this->ttl,
            $pollInterval,
            $this->creationDigest,
            $digest,
            $this->inputs,
            $this->cancellationRequestedAt,
            $this->cancellationVersion,
            previous: $this
        );
    }

    /**
     * Issues immutable bound requests through the same Task-local version fence
     */
    public function requestInput(TaskInputBatch $batch, int $expectedVersion, int $now): self
    {
        if ($expectedVersion !== $this->version) {
            throw new DomainException('Task input issuance conflicts.');
        }

        return $this->withInputs($this->inputs->issue($batch, $now), $now);
    }

    /**
     * Records validated active answers without reopening terminal state
     */
    public function answerInput(StrictJson $answers, string $caller, int $now): self
    {
        return $this->withInputs($this->inputs->answer($answers, $caller, $now), $now);
    }

    /**
     * Acquires a complete bound group durably before continuation
     */
    public function acquireInput(string $group, string $caller, int $now): self
    {
        return $this->withInputs($this->inputs->acquire($group, $caller, $now), $now);
    }

    /**
     * Removes a superseded payload without forgetting lifetime keys
     */
    public function retireInput(string $group, int $expectedVersion, int $now): self
    {
        if ($expectedVersion !== $this->version) {
            throw new DomainException('Task input retirement conflicts.');
        }

        return $this->withInputs($this->inputs->retire($group), $now);
    }

    /**
     * Records one bounded cooperative intent without replacing actual state or input evidence
     */
    public function requestCancellation(int $now): ?self
    {
        self::timestamp($now);
        if ($this->state->isTerminal() || $this->cancellationRequestedAt !== null) {
            return null;
        }

        if ($this->version === self::MAX_VERSION) {
            throw new DomainException('Task cancellation version exhausted.');
        }

        $admittedAt = max($this->updatedAt, $now);

        return new self(
            $this->definition,
            $this->state,
            $this->version + 1,
            $this->createdAt,
            $admittedAt,
            $this->ttl,
            $this->pollInterval,
            $this->creationDigest,
            self::digest(['cancellation', $this->version, $admittedAt]),
            $this->inputs,
            $admittedAt,
            $this->version + 1,
            previous: $this
        );
    }

    /**
     * Returns original intent evidence only for independently admitted protected observations
     */
    public function cancellation(): ?StrictJson
    {
        if ($this->cancellationRequestedAt === null) {
            return null;
        }

        return StrictJson::fromObject([
            'requested_at' => $this->cancellationRequestedAt,
            'version'      => $this->cancellationVersion
        ]);
    }

    /**
     * Returns protected retained input for separately authorized use cases
     */
    public function inputs(): TaskInputs
    {
        return $this->inputs;
    }

    /**
     * Returns protected original context for trusted admission and validation
     */
    public function definition(): TaskDefinition
    {
        return $this->definition;
    }

    /**
     * Returns coherent immutable state
     */
    public function state(): TaskState
    {
        return $this->state;
    }

    /**
     * Returns the Task-local concurrency fence rather than a global activity counter
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Returns coherent lifecycle state with only currently outstanding input
     */
    public function stateAt(int $now): TaskState
    {
        if ($this->state->status() !== 'input_required') {
            return $this->state;
        }

        $outstanding = $this->inputs->outstanding(self::timestamp($now));
        if ($outstanding->properties() === []) {
            return TaskState::create('working');
        }

        return TaskState::create('input_required', $this->state->data()->get('message'), $outstanding);
    }

    /**
     * Returns the original creation time in epoch milliseconds
     */
    public function createdAt(): int
    {
        return $this->createdAt;
    }

    /**
     * Returns the latest committed update time in epoch milliseconds
     */
    public function updatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * Returns the immutable creation-duration retention
     */
    public function ttl(): ?int
    {
        return $this->ttl;
    }

    /**
     * Returns the current polling advice
     */
    public function pollInterval(): ?int
    {
        return $this->pollInterval;
    }

    /**
     * Returns protected lifetime evidence without granting disclosure
     */
    public function incarnation(): string
    {
        return $this->revision->get('incarnation');
    }

    /**
     * Returns protected public-change order with bounded expiry sub-order
     */
    public function publicOrder(int $now): int
    {
        self::timestamp($now);
        if ($now < $this->updatedAt) {
            throw new DomainException('Task comparison clock moved backwards.');
        }

        $expiry = 0;
        if ($this->state->status() === 'input_required') {
            $expiry = count($this->inputs->expirations($this->updatedAt, $now));
        }

        // At most 16 retained groups can expire. A committed public write dominates every earlier expiry view,
        // even when independent reader/writer clocks straddle an expiry boundary.
        return (int) $this->revision->get('counter') * self::REVISION_STRIDE + $expiry;
    }

    /**
     * Returns bounded protected persistence data with only two fixed-size comparison digests
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema'                    => 1,
            'definition'                => $this->definition->toArray(),
            'state'                     => $this->state->toArray(),
            'version'                   => $this->version,
            'created_at'                => $this->createdAt,
            'updated_at'                => $this->updatedAt,
            'ttl'                       => $this->ttl,
            'poll_interval'             => $this->pollInterval,
            'creation_digest'           => $this->creationDigest,
            'last_digest'               => $this->lastDigest,
            'inputs'                    => $this->inputs->data(),
            'cancellation_requested_at' => $this->cancellationRequestedAt,
            'cancellation_version'      => $this->cancellationVersion,
            'revision'                  => $this->revision
        ];
    }

    /**
     * Creates exact encoded-content retry evidence without hidden historical payload copies
     *
     * @param array<mixed> $data
     */
    private static function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * Returns whether lifecycle state or readable metadata changed independently of private versions
     */
    private function changesPublicState(self $previous, int $now): bool
    {
        return $this->updatedAt !== $previous->updatedAt
            || $this->pollInterval !== $previous->pollInterval
            || !$this->stateAt($now)->data()->equals($previous->stateAt($now)->data());
    }

    /**
     * Records input-owned changes atomically with coherent outstanding status
     */
    private function withInputs(TaskInputs $inputs, int $now): self
    {
        if ($this->state->isTerminal() || $this->version === self::MAX_VERSION) {
            throw new DomainException('Task input conflicts with terminal state.');
        }

        $requests = $inputs->outstanding($now);
        $state = TaskState::create('working');
        if ($requests->properties() !== []) {
            $state = TaskState::create('input_required', input: $requests);
        }

        return new self(
            $this->definition,
            $state,
            $this->version + 1,
            $this->createdAt,
            max($this->updatedAt, $now),
            $this->ttl,
            $this->pollInterval,
            $this->creationDigest,
            self::digest([$inputs->data(), $this->version]),
            $inputs,
            $this->cancellationRequestedAt,
            $this->cancellationVersion,
            previous: $this
        );
    }
}
