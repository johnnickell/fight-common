<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;

/**
 * Class RemoveNotices
 */
final readonly class RemoveNotices implements Command
{
    /**
     * Constructs RemoveNotices
     */
    public function __construct(private NoticeRemovalSelection $selection)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(NoticeRemovalSelection::fromArray($data));
    }

    /**
     * Returns the explicit selection without treating references as credentials
     */
    public function selection(): NoticeRemovalSelection
    {
        return $this->selection;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->selection->toArray();
    }
}
