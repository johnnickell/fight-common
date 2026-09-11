<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Psr18\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Class NetworkException
 */
final class NetworkException extends ClientException implements NetworkExceptionInterface
{
    /**
     * Constructs NetworkException
     */
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
        ?Throwable $previous = null
    ) {
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
