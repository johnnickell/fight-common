<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class LengthMin
 */
final class LengthMin extends CompositeSpecification
{
    /**
     * Constructs LengthMin
     */
    public function __construct(private readonly int $minLength)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::minLength($candidate, $this->minLength);
    }
}
