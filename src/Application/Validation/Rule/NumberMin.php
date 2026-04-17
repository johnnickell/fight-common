<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class NumberMin
 */
final class NumberMin extends CompositeSpecification
{
    /**
     * Constructs NumberMin
     */
    public function __construct(private readonly int|float $minNumber)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::minNumber($candidate, $this->minNumber);
    }
}
