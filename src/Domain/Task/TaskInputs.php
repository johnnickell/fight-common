<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class TaskInputs
 */
final readonly class TaskInputs
{
    public const int MAX_KEYS = 1024;
    public const int MAX_BYTES = 32768;

    /**
     * Constructs TaskInputs
     *
     * @phpstan-param array<string, StrictJson> $groups Retained protected groups
     */
    private function __construct(private string $namespace, private int $issued, private array $groups)
    {
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $namespace) !== 1
            || $issued < 0 || $issued > self::MAX_KEYS || count($groups) > 16
        ) {
            throw new DomainException('Task input lifetime capacity exhausted.');
        }

        $keys = [];
        foreach ($groups as $id => $group) {
            $batch = TaskInputBatch::fromData($group->get('batch'));
            $requests = $batch->data()->get('requests')->properties();
            $answers = $group->get('answers');
            if (
                count($group->properties()) !== 4 || !is_bool($group->get('consumed'))
                || !is_bool($group->get('retired')) || !$answers instanceof StrictJson || !$answers->isObject()
                || array_diff(array_keys($answers->properties()), array_keys($requests)) !== []
                || $id !== array_key_first($requests)
            ) {
                throw new DomainException('Invalid retained Task input group.');
            }

            foreach ($requests as $key => $request) {
                if (
                    preg_match('/\Ainput-([0-9a-f]{32})-([1-9][0-9]{0,3})\z/D', $key, $match) !== 1
                    || $match[1] !== $namespace || (int) $match[2] > $issued || isset($keys[$key])
                ) {
                    throw new DomainException('Invalid retained Task input identity.');
                }

                $keys[$key] = true;
            }
        }

        if (count($keys) > 16 || strlen($this->data()->toString()) > self::MAX_BYTES) {
            throw new DomainException('Task input retained capacity exhausted.');
        }
    }

    /**
     * Creates empty lifetime bookkeeping without a payload archive
     */
    public static function empty(string $scope, string $id): self
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);

        return new self(substr(hash('sha256', $scope."\0".$id), 0, 32), 0, []);
    }

    /**
     * Creates validated retained bookkeeping from plain stored data
     */
    public static function fromData(StrictJson $data): self
    {
        if (!$data->isObject() || count($data->properties()) !== 3 || !$data->get('groups') instanceof StrictJson) {
            throw new DomainException('Invalid retained Task inputs.');
        }

        return new self($data->get('namespace'), $data->get('issued'), $data->get('groups')->properties());
    }

    /**
     * Returns whether retained request identity belongs to this exact Task and scope
     */
    public function belongsTo(string $scope, string $id): bool
    {
        return $this->namespace === self::empty($scope, $id)->namespace;
    }

    /**
     * Issues fresh keys without permitting caller-selected lifetime identity
     */
    public function issue(TaskInputBatch $batch, int $now): self
    {
        $data = $batch->data();
        if ($data->get('expires_at') <= $now) {
            throw new DomainException('Task input expired.');
        }

        $issued = $this->issued;
        $requests = [];
        foreach ($data->get('requests')->properties() as $request) {
            $requests['input-'.$this->namespace.'-'.++$issued] = $request;
        }

        $group = StrictJson::fromObject([
            'batch'    => $data->with('requests', StrictJson::fromObject($requests), 40),
            'answers'  => StrictJson::fromObject(),
            'consumed' => false,
            'retired'  => false
        ], 40);

        return new self($this->namespace, $issued, [...$this->groups, array_key_first($requests) => $group]);
    }

    /**
     * Returns active private contracts only for the exact bound caller
     *
     * @return array<string, StrictJson>
     */
    public function active(string $caller, int $now): array
    {
        $active = [];
        foreach ($this->groups as $group) {
            $batch = $group->get('batch');
            if (
                $batch->get('caller') !== $caller || $batch->get('expires_at') <= $now
                || $group->get('retired') || $group->get('consumed')
            ) {
                continue;
            }

            foreach ($batch->get('requests')->properties() as $key => $request) {
                if (!$group->get('answers')->has($key)) {
                    $active[$key] = $request->get('contract');
                }
            }
        }

        return $active;
    }

    /**
     * Returns all unexpired outstanding public requests without private rules or answers
     */
    public function outstanding(int $now): StrictJson
    {
        $requests = [];
        foreach ($this->groups as $group) {
            $batch = $group->get('batch');
            foreach ($this->active($batch->get('caller'), $now) as $key => $contract) {
                if ($batch->get('requests')->has($key)) {
                    $requests[$key] = $batch->get('requests')->get($key)->get('public');
                }
            }
        }

        return StrictJson::fromObject($requests, 40);
    }

    /**
     * Records a fully validated active batch without replacing earlier answers
     */
    public function answer(StrictJson $responses, string $caller, int $now): self
    {
        $active = $this->active($caller, $now);
        $groups = $this->groups;
        foreach ($groups as $id => $group) {
            $answers = $group->get('answers');
            foreach ($responses->properties() as $key => $response) {
                if (!isset($active[$key]) || !$group->get('batch')->get('requests')->has($key)) {
                    continue;
                }

                $answers = $answers->with($key, $response, 40);
                if ($group->get('batch')->get('confirmation') && $response->get('action') !== 'accept') {
                    $group = $group->with('retired', true, 40);
                }
            }

            $groups[$id] = $group->with('answers', $answers, 40);
        }

        return new self($this->namespace, $this->issued, $groups);
    }

    /**
     * Acquires one complete group once before any consumer continuation
     */
    public function acquire(string $id, string $caller, int $now): self
    {
        $group = $this->groups[$id] ?? null;
        if (
            $group === null || $group->get('batch')->get('caller') !== $caller
            || $group->get('batch')->get('expires_at') <= $now || $group->get('consumed') || $group->get('retired')
            || count($group->get('answers')->properties()) !== count(
                $group->get('batch')->get('requests')->properties()
            )
        ) {
            throw new DomainException('Task input continuation unavailable.');
        }

        return new self($this->namespace, $this->issued, [...$this->groups, $id => $group->with('consumed', true, 40)]);
    }

    /**
     * Removes one payload group while preserving the lifetime high-water mark
     */
    public function retire(string $id): self
    {
        if (!isset($this->groups[$id])) {
            throw new DomainException('Task input group unavailable.');
        }

        $groups = $this->groups;
        unset($groups[$id]);

        return new self($this->namespace, $this->issued, $groups);
    }

    /**
     * Returns protected durable answers and admission state without a receipt of execution
     */
    public function observe(string $caller, int $now, bool $terminal): StrictJson
    {
        $groups = [];
        foreach ($this->groups as $id => $group) {
            $batch = $group->get('batch');
            if ($batch->get('caller') !== $caller) {
                continue;
            }

            $complete = count($group->get('answers')->properties()) === count($batch->get('requests')->properties());
            $groups[$id] = StrictJson::fromObject([
                'answers'      => $group->get('answers'),
                'names'        => StrictJson::fromObject(array_map(
                    static fn(StrictJson $request): string => $request->get('name'),
                    $batch->get('requests')->properties()
                )),
                'consumed'     => $group->get('consumed'),
                'refused'      => $group->get('retired'),
                'confirmation' => $batch->get('confirmation'),
                'expires_at'   => $batch->get('expires_at'),
                'expired'      => $batch->get('expires_at') <= $now,
                'ready'        => !$group->get('retired') && !$group->get('consumed') && $complete
                    && $batch->get('expires_at') > $now && !$terminal
            ], 40);
        }

        return StrictJson::fromObject($groups, 40);
    }

    /**
     * Returns protected bounded persistence data including constant-size lifetime bookkeeping
     */
    public function data(): StrictJson
    {
        return StrictJson::fromObject([
            'namespace' => $this->namespace,
            'issued'    => $this->issued,
            'groups'    => StrictJson::fromObject($this->groups)
        ], 40);
    }
}
