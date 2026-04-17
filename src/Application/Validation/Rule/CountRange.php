<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class CountRange
 */
final class CountRange extends CompositeSpecification
{
    /**
     * Constructs CountRange
     */
    public function __construct(private readonly int $minCount, private readonly int $maxCount)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::rangeCount(
            $candidate,
            $this->minCount,
            $this->maxCount
        );
    }
}
