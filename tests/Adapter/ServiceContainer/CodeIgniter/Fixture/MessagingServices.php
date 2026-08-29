<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use Fight\Common\Adapter\Messaging\CodeIgniter\CommandMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\EventMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueCommandBus;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\MessagingServices;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use RuntimeException;

/**
 * Project-owned messaging-only Config\Services fixture.
 */
final class Services extends BaseService
{
    public static function fightQueueCommandBus(bool $getShared = true): QueueCommandBus
    {
        if ($getShared) {
            return static::getSharedInstance('fightQueueCommandBus');
        }

        return MessagingServices::queueCommandBus(static::fightQueue(), 'commands', 'fight-command');
    }

    public static function fightAsynchronousCommandBus(bool $getShared = true): AsynchronousCommandBus
    {
        return static::fightQueueCommandBus($getShared);
    }

    public static function fightQueueEventDispatcher(bool $getShared = true): QueueEventDispatcher
    {
        if ($getShared) {
            return static::getSharedInstance('fightQueueEventDispatcher');
        }

        return MessagingServices::queueEventDispatcher(static::fightQueue(), 'events', 'fight-event');
    }

    public static function fightAsynchronousEventDispatcher(bool $getShared = true): AsynchronousEventDispatcher
    {
        return static::fightQueueEventDispatcher($getShared);
    }

    public static function fightCommandMessageHandler(bool $getShared = true): CommandMessageHandler
    {
        if ($getShared) {
            return static::getSharedInstance(CommandMessageJob::HANDLER_SERVICE);
        }

        return MessagingServices::commandMessageHandler(static::fightSynchronousCommandBus());
    }

    public static function fightEventMessageHandler(bool $getShared = true): EventMessageHandler
    {
        if ($getShared) {
            return static::getSharedInstance(EventMessageJob::HANDLER_SERVICE);
        }

        return MessagingServices::eventMessageHandler(static::fightSynchronousEventDispatcher());
    }

    private static function fightQueue(): QueueInterface
    {
        $queue = static::get('fightQueueCollaborator');

        if (! $queue instanceof QueueInterface) {
            throw new RuntimeException('The project queue collaborator must implement QueueInterface.');
        }

        return $queue;
    }

    private static function fightSynchronousCommandBus(): SynchronousCommandBus
    {
        $commandBus = static::get('fightSynchronousCommandBusCollaborator');

        if (! $commandBus instanceof SynchronousCommandBus) {
            throw new RuntimeException('The project synchronous command bus collaborator is unavailable.');
        }

        return $commandBus;
    }

    private static function fightSynchronousEventDispatcher(): SynchronousEventDispatcher
    {
        $eventDispatcher = static::get('fightSynchronousEventDispatcherCollaborator');

        if (! $eventDispatcher instanceof SynchronousEventDispatcher) {
            throw new RuntimeException('The project synchronous event dispatcher collaborator is unavailable.');
        }

        return $eventDispatcher;
    }
}
