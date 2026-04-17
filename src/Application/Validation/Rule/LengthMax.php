<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class LengthMax
 */
final class LengthMax extends CompositeSpecification
{
    /**
     * Constructs LengthMax
     */
    public function __construct(private readonly int $maxLength)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::maxLength($candidate, $this->maxLength);
    }
}
