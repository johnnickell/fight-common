<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Fight\Common\Application\Auth\RequestService;
use Fight\Common\Application\Auth\WebhookDispatcher;
use Fight\Common\Application\HttpClient\Message\MessageFactory;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class HmacWebhookDispatcher
 *
 * @deprecated since 1.2, will be removed in 2.0. Use direct JSON payload
 * construction until MCP tooling is available as a future feature.
 */
final readonly class HmacWebhookDispatcher implements WebhookDispatcher
{
    private const array KNOWN_ACTIONS = [
        'health_check',
        'clear_cache',
        'run_migration',
        'deploy'
    ];

    /**
     * Constructs HmacWebhookDispatcher
     *
     * @param HttpClient    $client
     * @param MessageFactory $factory
     * @param RequestService $signer
     */
    public function __construct(
        private HttpClient $client,
        private MessageFactory $factory,
        private RequestService $signer
    ) {
    }

    /**
     * Authenticates and dispatches an outbound webhook by signing its request
     *
     * @param string               $url
     * @param string               $action
     * @param array<string, mixed> $payload
     *
     * @throws DomainException When the action is unknown
     */
    public function dispatch(string $url, string $action, array $payload = []): void
    {
        if (!in_array($action, self::KNOWN_ACTIONS, true)) {
            throw new DomainException(sprintf('Unknown AI operation action: %s', $action));
        }

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
