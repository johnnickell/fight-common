<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Application\HttpClient\Exception\Exception;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleClient
 */
final readonly class GuzzleClient implements HttpClient
{
    /**
     * Constructs GuzzleClient
     */
    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * @param RequestInterface $request
     * @param array<string, mixed> $options
     *
     * @inheritDoc
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $promise = $this->sendAsync($request, $options);
        $promise->wait();

        if ($promise->getState() === Promise::REJECTED) {
            $exception = $promise->getException();

            if (!($exception instanceof Exception)) {
                throw new TransferException(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            throw $exception;
        }

        return $promise->getResponse();
    }

    /**
     * @param RequestInterface $request
     * @param array<string, mixed> $options
     *
     * @inheritDoc
     */
    public function sendAsync(RequestInterface $request, array $options = []): Promise
    {
        $promise = $this->client->sendAsync($request, $options);

        return new GuzzlePromise($promise, $request);
    }
}
