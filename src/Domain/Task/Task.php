<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task;

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
        private TaskInputs $inputs
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
        ) {
            throw new DomainException('Invalid retained Task metadata.');
        }
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
            TaskInputs::empty($definition->scope(), $definition->id())
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
            'creation_digest', 'last_digest', 'inputs'
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
            TaskInputs::fromData(StrictJson::fromData($data['inputs']))
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
            $this->inputs
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
     * Returns bounded state without protected context or comparison evidence
     *
     * @return array<string, mixed>
     */
    public function view(?int $now = null): array
    {
        $state = $this->state;
        if ($now !== null && $state->status() === 'input_required') {
            $outstanding = $this->inputs->outstanding(self::timestamp($now));
            $state = TaskState::create('working');
            if ($outstanding->properties() !== []) {
                $state = TaskState::create('input_required', input: $outstanding);
            }
        }

        return [
            'id'            => $this->definition->id(),
            'version'       => $this->version,
            'state'         => $state->data(),
            'created_at'    => $this->createdAt,
            'updated_at'    => $this->updatedAt,
            'ttl'           => $this->ttl,
            'poll_interval' => $this->pollInterval
        ];
    }

    /**
     * Returns bounded protected persistence data with only two fixed-size comparison digests
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema'          => 1,
            'definition'      => $this->definition->toArray(),
            'state'           => $this->state->toArray(),
            'version'         => $this->version,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
            'ttl'             => $this->ttl,
            'poll_interval'   => $this->pollInterval,
            'creation_digest' => $this->creationDigest,
            'last_digest'     => $this->lastDigest,
            'inputs'          => $this->inputs->data()
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
            $inputs
        );
    }
}
