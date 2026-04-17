<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class NumberMax
 */
final class NumberMax extends CompositeSpecification
{
    /**
     * Constructs NumberMax
     */
    public function __construct(private readonly int|float $maxNumber)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::maxNumber($candidate, $this->maxNumber);
    }
}
