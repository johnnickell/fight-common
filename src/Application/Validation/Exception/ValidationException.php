<?php

declare(strict_types=1);

namespace Fight\Common\Application\Validation\Exception;

use Fight\Common\Domain\Exception\ValidationException as BaseException;

/**
 * Class ValidationException
 */
class ValidationException extends BaseException
{
    /**
     * Creates instance from validation errors
     * 
     * @param array<string, string[]> $errors
     */
    public static function fromErrors(array $errors): static
    {
        $message = 'Validation Failed';

        return new static($errors, $message);
    }
}
