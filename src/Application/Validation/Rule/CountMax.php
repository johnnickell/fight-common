<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class CountMax
 */
final class CountMax extends CompositeSpecification
{
    /**
     * Constructs CountMax
     */
    public function __construct(private readonly int $maxCount)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::maxCount($candidate, $this->maxCount);
    }
}
