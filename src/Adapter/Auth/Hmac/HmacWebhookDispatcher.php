<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\WebhookDispatcher;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Domain\Auth\AiOperation;

/**
 * Class HmacWebhookDispatcher
 */
final readonly class HmacWebhookDispatcher implements WebhookDispatcher
{
    /**
     * Constructs HmacWebhookDispatcher
     */
    public function __construct(
        private HttpClient $client,
        private MessageFactory $factory,
        private RequestService $signer
    ) {
    }

    /**
     * @param string $url
     * @param string $action
     * @param array<string, mixed> $payload
     *
     * @inheritDoc
     */
    public function dispatch(string $url, string $action, array $payload = []): void
    {
        $operation = AiOperation::fromArray(['action' => $action, 'payload' => $payload]);
        $body = json_encode($operation->toArray(), JSON_UNESCAPED_SLASHES);

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
