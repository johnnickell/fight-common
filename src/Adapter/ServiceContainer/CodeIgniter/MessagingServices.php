<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Queue\Interfaces\QueueInterface;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueCommandBus;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;

/**
 * Class MessagingServices
 *
 * A project-owned Config\Services class selects this capability and owns its
 * Queue configuration, worker policy, synchronous buses, and service aliases.
 */
final class MessagingServices
{
    /**
     * Creates the asynchronous command bus
     */
    public static function queueCommandBus(
        QueueInterface $queue,
        string $queueName,
        string $jobAlias
    ): QueueCommandBus {
        return new QueueCommandBus($queue, $queueName, $jobAlias);
    }

    /**
     * Creates the asynchronous command bus through its application contract
     */
    public static function asynchronousCommandBus(
        QueueInterface $queue,
        string $queueName,
        string $jobAlias
    ): AsynchronousCommandBus {
        return self::queueCommandBus($queue, $queueName, $jobAlias);
    }

    /**
     * Creates the asynchronous event dispatcher
     */
    public static function queueEventDispatcher(
        QueueInterface $queue,
        string $queueName,
        string $jobAlias
    ): QueueEventDispatcher {
        return new QueueEventDispatcher($queue, $queueName, $jobAlias);
    }

    /**
     * Creates the asynchronous event dispatcher through its application contract
     */
    public static function asynchronousEventDispatcher(
        QueueInterface $queue,
        string $queueName,
        string $jobAlias
    ): AsynchronousEventDispatcher {
        return self::queueEventDispatcher($queue, $queueName, $jobAlias);
    }

    /**
     * Creates the handler resolved by the configured command-job service alias
     */
    public static function commandMessageHandler(
        SynchronousCommandBus $synchronousCommandBus
    ): CommandMessageHandler {
        return new CommandMessageHandler($synchronousCommandBus);
    }

    /**
     * Creates the handler resolved by the configured event-job service alias
     */
    public static function eventMessageHandler(
        SynchronousEventDispatcher $synchronousEventDispatcher
    ): EventMessageHandler {
        return new EventMessageHandler($synchronousEventDispatcher);
    }
}
