<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Utility\VarPrinter;

/**
 * Class ValidationService
 */
final readonly class ValidationService
{
    private ValidationCoordinator $coordinator;

    /**
     * Constructs ValidationService
     */
    public function __construct(?ValidationCoordinator $coordinator = null)
    {
        $this->coordinator = $coordinator ?: new ValidationCoordinator();
    }

    /**
     * Performs validation on input with the given rules
     *
     * @throws ValidationException When validation fails
     * @throws DomainException When input or rules are formatted incorrectly
     */
    public function validate(array $input, array $rules): ApplicationData
    {
        $this->validateInput($input);
        $this->validateRules($rules);
        $this->addValidators($rules);

        $result = $this->coordinator->validate(new InputData($input));

        if ($result->isFailed()) {
            throw ValidationException::fromErrors($result->getErrors()->toArray());
        }

        return $result->getData();
    }

    /**
     * Adds a custom validator
     */
    public function addValidator(Validator $validator): void
    {
        $this->coordinator->addValidator($validator);
    }

    /**
     * Adds validators to the validation coordinator
     *
     * @throws DomainException When rules are formatted incorrectly
     */
    private function addValidators(array $rules): void
    {
        foreach (RulesParser::parse($rules) as $fieldName => $fieldRules) {
            foreach ($fieldRules as $rule) {
                $type = $rule['type'];
                $args = $rule['args'];
                $error = $rule['error'];

                $method = sprintf('add%sValidation', $type);
                $methodArgs = array_merge([$fieldName, $error], $args);

                // parser should throw exception
                // @codeCoverageIgnoreStart
                if (!method_exists($this->coordinator, $method)) {
                    $message = sprintf('Unsupported validation: %s', $type);
                    throw new DomainException($message);
                }
                // @codeCoverageIgnoreEnd

                call_user_func_array(
                    [$this->coordinator, $method],
                    $methodArgs
                );
            }
        }
    }

    /**
     * Validates validation rules
     *
     * @throws DomainException When rules are formatted incorrectly
     */
    private function validateRules(array $rules): void
    {
        /** @var array $rule */
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                $message = sprintf('Invalid rule definition: %s', VarPrinter::toString($rules));
                throw new DomainException($message);
            }
            if (!isset($rule['field'])) {
                $message = sprintf('Field is required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }
            if (!isset($rule['label'])) {
                $message = sprintf('Label is required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }
            if (!isset($rule['rules'])) {
                $message = sprintf('Rules are required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }
        }
    }

    /**
     * Validates input data
     *
     * @throws DomainException When input is formatted incorrectly
     */
    private function validateInput(array $input): void
    {
        if (!Validate::isListOf(array_keys($input), 'string')) {
            $message = sprintf('Input keys should be strings: %s', VarPrinter::toString($input));
            throw new DomainException($message);
        }
    }
}
