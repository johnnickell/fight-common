<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class KeyNotEmpty
 */
final class KeyNotEmpty extends CompositeSpecification
{
    /**
     * Constructs KeyNotEmpty
     */
    public function __construct(private readonly string $key)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::keyNotEmpty($candidate, $this->key);
    }
}
