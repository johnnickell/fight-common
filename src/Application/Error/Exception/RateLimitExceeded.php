<?php

declare(strict_types=1);

namespace Fight\Common\Application\Error\Exception;

use Fight\Common\Domain\Exception\DomainException;
use Throwable;

/**
 * Class RateLimitExceeded
 */
class RateLimitExceeded extends ApplicationException
{
    /**
     * Constructs RateLimitExceeded
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly ?int $retryDelaySeconds = null
    ) {
        if ($retryDelaySeconds !== null && $retryDelaySeconds < 0) {
            throw new DomainException('Retry delay must be nonnegative.');
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the optional neutral retry delay in seconds
     */
    public function retryDelaySeconds(): ?int
    {
        return $this->retryDelaySeconds;
    }
}
