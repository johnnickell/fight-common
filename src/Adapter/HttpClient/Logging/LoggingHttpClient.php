<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Logging;

use Fight\Common\Application\HttpClient\Message\Promise;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Class LoggingHttpClient
 */
final readonly class LoggingHttpClient implements HttpClient
{
    /**
     * Constructs LoggingHttpClient
     */
    public function __construct(
        private HttpClient $httpClient,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::DEBUG
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $promise = $this->sendAsync($request, $options);
        $promise->wait();

        if ($promise->getState() === Promise::REJECTED) {
            throw $promise->getException();
        }

        return $promise->getResponse();
    }

    /**
     * @inheritDoc
     */
    public function sendAsync(RequestInterface $request, array $options = []): Promise
    {
        $this->logger->log(
            $this->logLevel,
            '[HTTP]: Outgoing HTTP Request',
            [
                'method'   => $request->getMethod(),
                'uri'      => (string) $request->getUri(),
                'protocol' => $request->getProtocolVersion(),
                'headers'  => $request->getHeaders(),
                'content'  => (string) $request->getBody()
            ]
        );

        $promise = $this->httpClient->sendAsync($request, $options);

        return $promise->then(
            function (ResponseInterface $response) {
                $stream = $response->getBody();

                $this->logger->log(
                    $this->logLevel,
                    '[HTTP]: Incoming HTTP Response',
                    [
                        'status'   => $response->getStatusCode(),
                        'reason'   => $response->getReasonPhrase(),
                        'protocol' => $response->getProtocolVersion(),
                        'headers'  => $response->getHeaders(),
                        'content'  => $stream->getContents()
                    ]
                );

                $stream->rewind();

                return $response;
            },
            function (Throwable $exception): void {
                $this->logger->log(
                    $this->logLevel,
                    '[HTTP]: HTTP Error Exception',
                    [
                        'message'   => $exception->getMessage(),
                        'exception' => $exception
                    ]
                );

                throw $exception;
            }
        );
    }
}
