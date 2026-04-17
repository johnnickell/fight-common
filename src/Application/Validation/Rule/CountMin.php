<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class CountMin
 */
final class CountMin extends CompositeSpecification
{
    /**
     * Constructs CountMin
     */
    public function __construct(private readonly int $minCount)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::minCount($candidate, $this->minCount);
    }
}
