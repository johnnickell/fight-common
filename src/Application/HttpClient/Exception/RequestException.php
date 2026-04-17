<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpClient\Exception;

use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Class RequestException
 */
class RequestException extends TransferException
{
    /**
     * Constructs RequestException
     */
    public function __construct(string $message, protected RequestInterface $request, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Retrieves the request
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
