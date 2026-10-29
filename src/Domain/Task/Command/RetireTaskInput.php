<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Task\TaskDefinition;

/**
 * Class RetireTaskInput
 */
final readonly class RetireTaskInput implements Command
{
    /**
     * Constructs RetireTaskInput
     */
    public function __construct(
        public string $scope,
        public string $id,
        public string $producer,
        public int $expectedVersion,
        public string $group
    ) {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($producer);
        TaskDefinition::reference($group);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id'], $data['producer'], $data['expected_version'], $data['group']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'scope'            => $this->scope,
            'id'               => $this->id,
            'producer'         => $this->producer,
            'expected_version' => $this->expectedVersion,
            'group'            => $this->group
        ];
    }
}
