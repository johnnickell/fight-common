<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Fight\Common\Domain\Notice\Notice;

/**
 * Interface McpSubscriptionAdmission
 */
interface McpSubscriptionAdmission
{
    /**
     * Returns the trusted request-scoped subscriber reference, never a credential
     */
    public function subscriber(): string;

    /**
     * Returns current permission for the entire signal and all its source metadata
     *
     * Null notice checks opening disclosure; catalog activity requires its own explicit grant
     */
    public function allows(string $subscriber, string $filter, ?string $uri, ?Notice $notice): bool;
}
