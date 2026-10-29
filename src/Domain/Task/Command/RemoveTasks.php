<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Task\TaskSelection;

/**
 * Class RemoveTasks
 */
final readonly class RemoveTasks implements Command
{
    /**
     * Constructs RemoveTasks
     */
    public function __construct(private TaskSelection $selection)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(TaskSelection::fromIds($data['scope'], $data['ids']));
    }

    /**
     * Returns the explicit bounded selection rather than an eligibility policy
     */
    public function selection(): TaskSelection
    {
        return $this->selection;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->selection->scope(), 'ids' => $this->selection->ids()];
    }
}
