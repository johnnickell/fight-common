<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Serialization;

use Fight\Common\Domain\Messaging\Command\Command;

class SampleCommand implements Command
{
    public function __construct(private readonly string $value = '') {}

    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }

    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
