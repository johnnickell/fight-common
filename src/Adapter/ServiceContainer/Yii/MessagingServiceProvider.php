<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Class MessagingServiceProvider
 *
 * Registers reusable synchronous message handlers without a queue integration.
 */
final class MessagingServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns synchronous messaging definitions without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            CommandMessageHandler::class => CommandMessageHandler::class,
            EventMessageHandler::class   => EventMessageHandler::class
        ];
    }

    /**
     * Returns no messaging extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
