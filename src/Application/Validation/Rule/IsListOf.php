<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IsListOf
 */
final class IsListOf extends CompositeSpecification
{
    /**
     * Constructs IsListOf
     */
    public function __construct(private readonly string $type)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::isListOf($candidate, $this->type);
    }
}
