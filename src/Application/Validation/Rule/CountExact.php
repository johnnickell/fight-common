<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class CountExact
 */
final class CountExact extends CompositeSpecification
{
    /**
     * Constructs CountExact
     */
    public function __construct(private readonly int $count)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::exactCount($candidate, $this->count);
    }
}
