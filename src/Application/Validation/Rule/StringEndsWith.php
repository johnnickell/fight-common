<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class StringEndsWith
 */
final class StringEndsWith extends CompositeSpecification
{
    /**
     * Constructs StringEndsWith
     */
    public function __construct(private readonly string $search)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::endsWith($candidate, $this->search);
    }
}
