<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class IsMatch
 */
final class IsMatch extends CompositeSpecification
{
    /**
     * Constructs IsMatch
     */
    public function __construct(private readonly string $pattern)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::isMatch($candidate, $this->pattern);
    }
}
