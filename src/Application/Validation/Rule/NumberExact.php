<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class NumberExact
 */
final class NumberExact extends CompositeSpecification
{
    /**
     * Constructs NumberExact
     */
    public function __construct(private readonly int|float $number)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::exactNumber($candidate, $this->number);
    }
}
