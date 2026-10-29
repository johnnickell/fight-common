<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Task\TaskDefinition;

/**
 * Class AcquireTaskInput
 */
final readonly class AcquireTaskInput implements Command
{
    /**
     * Constructs AcquireTaskInput
     */
    public function __construct(public string $scope, public string $id, public string $caller, public string $group)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::identity($id);
        TaskDefinition::reference($caller);
        TaskDefinition::reference($group);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self($data['scope'], $data['id'], $data['caller'], $data['group']);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->scope, 'id' => $this->id, 'caller' => $this->caller, 'group' => $this->group];
    }
}
