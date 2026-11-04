<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use Fight\Common\Application\Mcp\Subscription\McpSubscriptionStore;
use Throwable;

/**
 * Class ObservedSubscriptionStore
 */
final class ObservedSubscriptionStore implements McpSubscriptionStore
{
    public array $calls = [];

    /**
     * Constructs ObservedSubscriptionStore
     */
    public function __construct(private readonly McpSubscriptionStore $subscriptionStore)
    {
    }

    /**
     * @inheritDoc
     */
    public function open(string $id, string $owner, array $context, int $now, int $expires): void
    {
        $this->subscriptionStore->open($id, $owner, $context, $now, $expires);
    }

    /**
     * @inheritDoc
     */
    public function heartbeat(string $id, string $owner, int $now, int $expires): void
    {
        $this->calls[] = ['operation' => 'heartbeat', 'now' => $now, 'expires' => $expires, 'entered_at' => microtime(true)];
        $callIndex = array_key_last($this->calls);
        try {
            $this->subscriptionStore->heartbeat($id, $owner, $now, $expires);
            $this->calls[$callIndex]['result'] = 'returned';
        } catch (Throwable $failure) {
            $this->calls[$callIndex]['result'] = $failure::class;
            throw $failure;
        }
    }

    /**
     * @inheritDoc
     */
    public function close(string $id, string $owner, int $now): void
    {
        $this->calls[] = ['operation' => 'close', 'now' => $now, 'entered_at' => microtime(true)];
        $callIndex = array_key_last($this->calls);
        try {
            $this->subscriptionStore->close($id, $owner, $now);
            $this->calls[$callIndex]['result'] = 'returned';
        } catch (Throwable $failure) {
            $this->calls[$callIndex]['result'] = $failure::class;
            throw $failure;
        }
    }

    /**
     * @inheritDoc
     */
    public function cleanup(int $now, int $limit): int
    {
        return $this->subscriptionStore->cleanup($now, $limit);
    }
}
