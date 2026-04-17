<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Domain\Specification\Specification;

/**
 * Class BasicValidator
 */
final readonly class BasicValidator implements Validator
{
    /**
     * Constructs BasicValidator
     */
    public function __construct(
        private Specification $specification,
        private string $fieldName,
        private string $errorMessage
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(ValidationContext $context): bool
    {
        if (!$this->specification->isSatisfiedBy($context)) {
            $context->addError($this->fieldName, $this->errorMessage);

            return false;
        }

        return true;
    }
}
