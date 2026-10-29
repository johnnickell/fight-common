<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task\Command;

use Fight\Common\Domain\Mcp\Task\TaskSelection;
use Fight\Common\Domain\Messaging\Command\Command;

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
