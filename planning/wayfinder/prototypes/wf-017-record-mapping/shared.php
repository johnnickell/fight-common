<?php

declare(strict_types=1);

/** PROTOTYPE — framework-neutral aggregate used unchanged in every lane. */
final readonly class MappingUser
{
    /** @param list<string> $roleIds */
    private function __construct(
        private string $id,
        private string $email,
        private array $roleIds,
    ) {}

    /** @param list<string> $roleIds */
    public static function reconstitute(string $id, string $email, array $roleIds): self
    {
        $roleIds = array_values(array_unique($roleIds));
        sort($roleIds);

        return new self($id, $email, $roleIds);
    }

    /** @param list<string> $roleIds */
    public function revised(string $email, array $roleIds): self
    {
        return self::reconstitute($this->id, $email, $roleIds);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function roleIds(): array
    {
        return $this->roleIds;
    }

    /** @return array{id: string, email: string, role_ids: list<string>} */
    public function snapshot(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'role_ids' => $this->roleIds];
    }
}

interface MappingUserRepository
{
    public function save(MappingUser $user): void;

    public function get(string $id): MappingUser;

    /** @return array{users: int, roles: int, assignments: int} */
    public function counts(): array;

    /** @return list<class-string> */
    public function recordTypes(): array;
}

/**
 * @param array<string, string|null> $versions
 * @return array<string, mixed>
 */
function runMappingProbe(
    string $lane,
    array $versions,
    MappingUserRepository $repository,
    string $strategy,
    string $relationshipMechanism,
    string $fitness,
): array {
    $original = MappingUser::reconstitute(
        'user-1',
        'before@example.test',
        ['role-admin', 'role-editor'],
    );
    $repository->save($original);
    $firstLoad = $repository->get('user-1');
    prototypeAssert($firstLoad instanceof MappingUser, 'repository must return the portable aggregate');
    prototypeAssert($firstLoad->snapshot() === $original->snapshot(), 'initial aggregate and roles must round trip');

    $revised = $firstLoad->revised('after@example.test', ['role-editor', 'role-auditor']);
    $repository->save($revised);
    $secondLoad = $repository->get('user-1');
    prototypeAssert($secondLoad->snapshot() === $revised->snapshot(), 'aggregate update and exact role replacement must round trip');
    prototypeAssert(
        $repository->counts() === ['users' => 1, 'roles' => 3, 'assignments' => 2],
        'storage must contain one user, three role definitions, and the exact two assignments',
    );

    foreach ($repository->recordTypes() as $recordType) {
        prototypeAssert(!is_a($recordType, MappingUser::class, true), 'framework records must stay distinct from the domain aggregate');
    }

    return [
        'prototype' => 'WF-017 record-to-aggregate mapping',
        'lane' => $lane,
        'versions' => $versions,
        'strategy' => $strategy,
        'relationship_mechanism' => $relationshipMechanism,
        'fitness' => $fitness,
        'observations' => [
            'portable_aggregate_unchanged' => true,
            'framework_record_not_returned' => true,
            'identity_preserved' => true,
            'initial_relationship_round_trip' => true,
            'exact_relationship_replacement' => true,
            'record_types' => $repository->recordTypes(),
        ],
        'final_state' => $secondLoad->snapshot(),
        'storage_counts' => $repository->counts(),
    ];
}

function prototypeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $receipt */
function printReceipt(array $receipt): never
{
    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $receiptPath = getenv('PROTOTYPE_RECEIPT');
    if (is_string($receiptPath) && $receiptPath !== '') {
        file_put_contents($receiptPath, $json);
    }

    echo $json;
    exit(0);
}
