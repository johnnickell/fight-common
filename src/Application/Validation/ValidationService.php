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
     * @param array<string, mixed> $input
     * @param array<int, array{field: string, label: string, rules: string}> $rules
     *
     * @throws ValidationException When validation fails
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
     * @param array<int, array{field: string, label: string, rules: string}> $rules
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
     * @param array<int, array{field: string, label: string, rules: string}> $rules
     *
     * @throws DomainException When rules are formatted incorrectly
     */
    private function validateRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) { // @phpstan-ignore function.alreadyNarrowedType
                $message = sprintf('Invalid rule definition: %s', VarPrinter::toString($rules));
                throw new DomainException($message);
            }

            // @phpstan-ignore isset.offset
            if (!isset($rule['field'])) {
                $message = sprintf('Field is required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }

            // @phpstan-ignore isset.offset
            if (!isset($rule['label'])) {
                $message = sprintf('Label is required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }

            // @phpstan-ignore isset.offset
            if (!isset($rule['rules'])) {
                $message = sprintf('Rules are required: %s', VarPrinter::toString($rule));
                throw new DomainException($message);
            }
        }
    }

    /**
     * Validates input data
     *
     * @param array<string, mixed> $input
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
