<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Exception;

use Throwable;

/**
 * Class ValidationException
 */
class ValidationException extends RuntimeException
{
    /**
     * Constructs ValidationException
     * 
     * @param array<string, string[]> $errors
     */
    public function __construct(private readonly array $errors, ?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? 'Validation failed', 0, $previous);
    }

    /**
     * Retrieves the validation errors
     * 
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
