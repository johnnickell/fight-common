<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

/**
 * Interface McpSubscriptionStore
 */
interface McpSubscriptionStore
{
    /**
     * Creates one bounded owner-bound registration on an idle dedicated connection
     *
     * @phpstan-param array<string, mixed> $context
     */
    public function open(string $id, string $owner, array $context, int $now, int $expires): void;

    /**
     * Updates only a still-fresh open registration owned by this execution
     *
     * The supplied clock is current at invocation; a serialized renewal must account for elapsed waiting time.
     */
    public function heartbeat(string $id, string $owner, int $now, int $expires): void;

    /**
     * Sets closure only for an open owned registration without resurrection or receipt inference
     */
    public function close(string $id, string $owner, int $now): void;

    /**
     * Removes at most the specified expired or closed technical rows
     */
    public function cleanup(int $now, int $limit): int;
}
