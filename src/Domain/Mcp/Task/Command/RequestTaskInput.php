<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Command;

use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskInputBatch;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class RequestTaskInput
 */
final readonly class RequestTaskInput implements Command
{
    /**
     * Constructs RequestTaskInput
     */
    public function __construct(
        public string $scope,
        public string $id,
        public string $producer,
        public int $expectedVersion,
        public TaskInputBatch $batch
    ) {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($producer);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['scope'],
            $data['id'],
            $data['producer'],
            $data['expected_version'],
            TaskInputBatch::fromData(StrictJson::fromData($data['batch']))
        );
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
            'batch'            => $this->batch->data()
        ];
    }
}
