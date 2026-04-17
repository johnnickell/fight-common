<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class EqualFieldsSpecification
 */
final class EqualFieldsSpecification extends CompositeSpecification
{
    /**
     * Constructs EqualFieldsSpecification
     */
    public function __construct(private readonly string $fieldName1, private readonly string $fieldName2)
    {
    }

    /**
     * Checks if the context satisfies the validation rule
     */
    public function isSatisfiedBy(mixed $context): bool
    {
        assert(Validate::isInstanceOf($context, ValidationContext::class));

        try {
            return Validate::areEqual(
                $context->get($this->fieldName1),
                $context->get($this->fieldName2)
            );
        } catch (KeyException) {
            return true;
        }
    }
}
