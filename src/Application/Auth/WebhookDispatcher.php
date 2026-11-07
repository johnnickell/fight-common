<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth;

use Fight\Common\Application\Mcp\Operation\WebhookDispatcher as McpWebhookDispatcher;

/**
 * Interface WebhookDispatcher
 *
 * @deprecated since 1.2, removal remains reserved for 2.0
 * Use the equally deprecated Mcp\Operation\WebhookDispatcher while migrating legacy AI integrations.
 */
interface WebhookDispatcher extends McpWebhookDispatcher
{
}
