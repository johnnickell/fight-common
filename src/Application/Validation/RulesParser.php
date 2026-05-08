<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StringObject;
use ReflectionClass;

/**
 * Class RulesParser
 */
final class RulesParser
{
    /**
     * Parses validation rules
     *
     * @throws DomainException When rules are formatted incorrectly
     */
    public static function parse(array $rules): array
    {
        $output = [];

        /** @var array $rule */
        foreach ($rules as $rule) {
            if (!isset($rule['field'])) {
                throw new DomainException('Rule definition is missing required key: field');
            }
            if (!is_string($rule['field'])) {
                throw new DomainException('Rule definition key "field" must be a string');
            }
            if (!isset($rule['label'])) {
                throw new DomainException('Rule definition is missing required key: label');
            }
            if (!isset($rule['rules'])) {
                throw new DomainException('Rule definition is missing required key: rules');
            }

            $fieldName = $rule['field'];
            $label = $rule['label'];

            if (!isset($output[$fieldName])) {
                $output[$fieldName] = [];
            }

            $matchString = null;
            if (StringObject::create($rule['rules'])->contains('match[')) {
                [$rulesString, $matchString] = static::extractMatchString(
                    $rule['rules']
                );
                $rule['rules'] = $rulesString;
            }

            $validations = explode('|', (string) $rule['rules']);

            if ($matchString !== null) {
                $validations[] = $matchString;
            }

            foreach ($validations as $validation) {
                if (empty($validation)) {
                    continue;
                }

                $validation = StringObject::create($validation);

                if (!$validation->contains('[')) {
                    $ruleName = $validation;

                    $error = static::getErrorMessage(
                        $rule,
                        $ruleName->toString(),
                        $label
                    );

                    $output[$fieldName][] = [
                        'type'  => $ruleName->toPascalCase()->toString(),
                        'args'  => [],
                        'error' => $error
                    ];
                } else {
                    $ruleName = $validation->substr(
                        0,
                        $validation->indexOf('[')
                    );

                    $ruleArgs = $validation->slice(
                        $validation->indexOf('[') + 1,
                        $validation->lastIndexOf(']')
                    );
                    $args = array_map(trim(...), explode(',', $ruleArgs->toString()));

                    // validate date/time formats
                    $dateTimeRules = ['date', 'time', 'date_time'];
                    if (in_array($ruleName->toString(), $dateTimeRules)) {
                        if (!isset($args[0]) || empty($args[0])) {
                            $message = sprintf('%s validation requires format', $ruleName->toString());
                            throw new DomainException($message);
                        }
                        $format = $args[0];
                        static::validateDateTimeFormat(
                            $ruleName->toString(),
                            $format
                        );
                    }

                    $error = static::getErrorMessage(
                        $rule,
                        $ruleName->toString(),
                        $label,
                        $args
                    );

                    $output[$fieldName][] = [
                        'type'  => $ruleName->toPascalCase()->toString(),
                        'args'  => $args,
                        'error' => $error
                    ];
                }
            }
        }

        return $output;
    }

    /**
     * Validates date/time format
     *
     * @throws DomainException When the format is invalid
     */
    private static function validateDateTimeFormat(string $ruleName, string $format): void
    {
        $unreservedChars = '\s+-_.:;,\/\[\]\(\)\|';
        $dateFormats = 'dDjlNSwzWFmMntLoYy';
        $timeFormats = 'aABgGhHisuveIOPTZ';
        $dateTimeFormats = sprintf('crU%s%s', $dateFormats, $timeFormats);
        $regexFormat = '/^([%s%s]+|[\\\\]{1}.{1})+$/';

        switch ($ruleName) {
            case 'date':
                $pattern = sprintf(
                    $regexFormat,
                    $dateFormats,
                    $unreservedChars
                );
                if (!preg_match($pattern, $format)) {
                    $message = sprintf('Invalid date format "%s"', $format);
                    throw new DomainException($message);
                }
                break;
            case 'time':
                $pattern = sprintf(
                    $regexFormat,
                    $timeFormats,
                    $unreservedChars
                );
                if (!preg_match($pattern, $format)) {
                    $message = sprintf('Invalid time format "%s"', $format);
                    throw new DomainException($message);
                }
                break;
            case 'date_time':
                $pattern = sprintf(
                    $regexFormat,
                    $dateTimeFormats,
                    $unreservedChars
                );
                if (!preg_match($pattern, $format)) {
                    $message = sprintf('Invalid date/time format "%s"', $format);
                    throw new DomainException($message);
                }
                break;
        }
    }

    /**
     * Retrieves the error message for a rule
     *
     * @throws DomainException When rules are formatted incorrectly
     */
    private static function getErrorMessage(array $rule, string $ruleName, string $label, array $args = []): string
    {
        $errorKey = StringObject::create($ruleName)
            ->toUpperUnderscored()
            ->toString();

        if (!defined(sprintf('%s::%s', ErrorMessages::class, $errorKey))) {
            $message = sprintf('Unsupported rule name: %s', $ruleName);
            throw new DomainException($message);
        }

        $format = ErrorMessages::{$errorKey};

        if (isset($rule['errors'][$ruleName])) {
            $format = $rule['errors'][$ruleName];
        }

        if ($ruleName === 'in_list') {
            $listString = sprintf(
                '[%s]',
                rtrim(str_repeat('%s,', count($args)), ',')
            );
            $format = str_replace('{{list}}', $listString, $format);
        }

        return call_user_func_array(
            sprintf(...),
            array_merge([$format, $label], $args)
        );
    }

    /**
     * Extracts match portion of the rules string
     *
     * @throws DomainException
     */
    protected static function extractMatchString(string $rulesString): array
    {
        $rules = StringObject::create($rulesString);
        $matchString = '';

        $startPos = $rules->indexOf('match[');
        $remainingParts = $rules->substr($startPos)->split('|');
        $ruleSet = array_change_key_case(
            new ReflectionClass(ErrorMessages::class)->getConstants(),
            CASE_LOWER
        );
        /** @var StringObject $part */
        foreach ($remainingParts as $part) {
            if ($part->startsWith('match[')) {
                $matchString .= $part->toString();
                continue;
            }
            if ($part->indexOf('[') !== -1) {
                $ruleName = $part->substr(0, $part->indexOf('['))->toString();
                if (isset($ruleSet[$ruleName])) {
                    break;
                }
            }
            $matchString .= sprintf('|%s', $part->toString());
        }

        return [str_replace($matchString, '', $rulesString), $matchString];
    }
}
