<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Specification;

/**
 * Class NotSpecification
 */
final class NotSpecification extends CompositeSpecification
{
    /**
     * Constructs NotSpecification
     */
    public function __construct(private readonly Specification $spec)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->spec->isSatisfiedBy($candidate);
    }
}
