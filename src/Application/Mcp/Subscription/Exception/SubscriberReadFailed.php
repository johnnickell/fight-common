<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Exception;

use RuntimeException;
use Throwable;

/**
 * Class SubscriberReadFailed
 */
final class SubscriberReadFailed extends RuntimeException
{
    /**
     * Constructs SubscriberReadFailed
     */
    public function __construct(Throwable $previous)
    {
        parent::__construct('Subscriber inspection unavailable.', 0, $previous);
    }
}
