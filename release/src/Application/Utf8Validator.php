<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class Utf8Validator
 *
 * Keeps public release inputs and machine results JSON-representable.
 */
final readonly class Utf8Validator
{
    /**
     * Checks every string key and value in one release value tree
     */
    public function isValid(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match('//u', $value) === 1;
        }

        if (!is_array($value)) {
            return true;
        }

        foreach ($value as $key => $entry) {
            if (is_string($key) && preg_match('//u', $key) !== 1) {
                return false;
            }

            if (!$this->isValid($entry)) {
                return false;
            }
        }

        return true;
    }
}
