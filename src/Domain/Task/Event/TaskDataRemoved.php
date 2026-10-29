<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Task\Event;

use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Task\TaskSelection;

/**
 * Class TaskDataRemoved
 */
final readonly class TaskDataRemoved implements Event
{
    /**
     * Constructs TaskDataRemoved
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
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->selection->scope(), 'ids' => $this->selection->ids()];
    }
}
