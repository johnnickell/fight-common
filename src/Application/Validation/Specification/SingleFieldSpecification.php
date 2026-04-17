<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Specification\Specification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class SingleFieldSpecification
 */
final class SingleFieldSpecification extends CompositeSpecification
{
    /**
     * Constructs SingleFieldSpecification
     */
    public function __construct(private readonly string $fieldName, private readonly Specification $rule)
    {
    }

    /**
     * Checks if the context satisfies the validation rule
     */
    public function isSatisfiedBy(mixed $context): bool
    {
        assert(Validate::isInstanceOf($context, ValidationContext::class));

        try {
            return $this->rule->isSatisfiedBy($context->get($this->fieldName));
        } catch (KeyException) {
            return true;
        }
    }
}
