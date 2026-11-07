<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Fight\Common\Adapter\Mcp\Operation\Hmac\HmacWebhookDispatcher as McpHmacWebhookDispatcher;
use Fight\Common\Application\Auth\WebhookDispatcher;

/**
 * Class HmacWebhookDispatcher
 *
 * @deprecated since 1.2, removal remains reserved for 2.0
 * Use the equally deprecated Mcp\Operation\Hmac adapter while migrating legacy AI integrations.
 */
final readonly class HmacWebhookDispatcher extends McpHmacWebhookDispatcher implements WebhookDispatcher
{
}
