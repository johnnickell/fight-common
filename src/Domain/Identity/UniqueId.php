<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Identity;

use Override;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class UniqueId
 */
abstract readonly class UniqueId extends ValueObject implements Identifier, IdentifierFactory
{
    /**
     * Constructs UniqueId
     */
    protected function __construct(protected Uuid $uuid)
    {
    }

    /**
     * Generates a unique identifier
     */
    public static function generate(): static
    {
        return new static(Uuid::comb());
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        return new static(Uuid::parse($value));
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->uuid->toString();
    }

    /**
     * @inheritDoc
     */
    public function compareTo(self $other): int
    {
        if ($this === $other) {
            return 0;
        }

        return $this->uuid->compareTo($other->uuid);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function equals(mixed $object): bool
    {
        if ($this === $object) {
            return true;
        }

        if (!Validate::areSameType($this, $object)) {
            return false;
        }

        return $this->uuid->equals($object->uuid);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hashValue(): string
    {
        return sprintf(
            '%s:%s',
            ClassName::canonical(static::class),
            $this->uuid->hashValue()
        );
    }
}
