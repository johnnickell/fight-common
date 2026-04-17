<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Specification;

use Fight\Common\Application\Validation\ValidationContext;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class RequiredFieldSpecification
 */
final class RequiredFieldSpecification extends CompositeSpecification
{
    /**
     * Constructs RequiredFieldSpecification
     */
    public function __construct(protected string $fieldName)
    {
    }

    /**
     * Checks if the context satisfies the validation rule
     */
    public function isSatisfiedBy(mixed $context): bool
    {
        assert(Validate::isInstanceOf($context, ValidationContext::class));

        try {
            $context->get($this->fieldName);

            return true;
        } catch (KeyException) {
            return false;
        }
    }
}
