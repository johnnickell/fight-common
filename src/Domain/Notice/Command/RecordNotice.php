<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice\Command;

use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Notice\Notice;

/**
 * Class RecordNotice
 */
final readonly class RecordNotice implements Command
{
    /**
     * Constructs RecordNotice
     */
    public function __construct(private Notice $notice)
    {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        return new self(Notice::fromArray($data));
    }

    /**
     * Returns the bounded submission without treating its fields as credentials
     */
    public function notice(): Notice
    {
        return $this->notice;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->notice->toArray();
    }
}
