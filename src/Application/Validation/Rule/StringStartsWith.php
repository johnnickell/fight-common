<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Rule;

use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class StringStartsWith
 */
final class StringStartsWith extends CompositeSpecification
{
    /**
     * Constructs StringStartsWith
     */
    public function __construct(private readonly string $search)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return Validate::startsWith($candidate, $this->search);
    }
}
