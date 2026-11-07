<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Observability\Health;

use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Application\Observability\HealthCheck;
use Fight\Common\Domain\Observability\HealthResult;
use Fight\Common\Domain\Observability\HealthStatus;
use Throwable;

/**
 * Class HttpEndpointHealthCheck
 */
final readonly class HttpEndpointHealthCheck implements HealthCheck
{
    private HttpClient $httpClient;
    private MessageFactory $messageFactory;

    /**
     * Constructs HttpEndpointHealthCheck
     */
    public function __construct(
        HttpClient $client,
        MessageFactory $factory,
        private string $url,
        private string $checkName = 'http'
    ) {
        $this->httpClient = $client;
        $this->messageFactory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->checkName;
    }

    /**
     * @inheritDoc
     */
    public function check(): HealthResult
    {
        try {
            $request = $this->messageFactory->createRequest('GET', $this->url);
            $start = hrtime(true);
            $response = $this->httpClient->send($request);
            $elapsed = round((hrtime(true) - $start) / 1e6, 2);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return new HealthResult(
                    $this->checkName,
                    HealthStatus::healthy(),
                    sprintf('%d in %sms', $statusCode, $elapsed)
                );
            }

            return new HealthResult(
                $this->checkName,
                HealthStatus::unhealthy(),
                sprintf('HTTP %d', $statusCode)
            );
        } catch (Throwable $throwable) {
            return new HealthResult(
                $this->checkName,
                HealthStatus::unhealthy(),
                $throwable->getMessage()
            );
        }
    }
}
