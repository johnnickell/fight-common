<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\WebhookDispatcher;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;

/**
 * @deprecated since 1.2, will be removed in 2.0. Use direct JSON payload construction
 * until MCP tooling is available as a future feature.
 */
final readonly class HmacWebhookDispatcher implements WebhookDispatcher
{
    public function __construct(
        private HttpClient $client,
        private MessageFactory $factory,
        private RequestService $signer
    ) {
    }

    public function dispatch(string $url, string $action, array $payload = []): void
    {
        $body = json_encode(['action' => $action, 'payload' => $payload], JSON_UNESCAPED_SLASHES);

        $request = $this->factory->createRequest(
            'POST',
            $url,
            ['Content-Type' => 'application/json'],
            $body
        );

        $signed = $this->signer->signRequest($request);

        $this->client->send($signed);
    }
}
