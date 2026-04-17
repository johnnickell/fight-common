<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Specification;

/**
 * Class OrSpecification
 */
final class OrSpecification extends CompositeSpecification
{
    /**
     * Constructs OrSpecification
     */
    public function __construct(
        private readonly Specification $firstSpec,
        private readonly Specification $secondSpec
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->firstSpec->isSatisfiedBy($candidate) || $this->secondSpec->isSatisfiedBy($candidate);
    }
}
