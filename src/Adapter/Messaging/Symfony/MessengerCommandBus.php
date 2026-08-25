<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Symfony;

use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Class MessengerCommandBus
 */
final readonly class MessengerCommandBus implements AsynchronousCommandBus
{
    /**
     * Constructs MessengerCommandBus
     */
    public function __construct(private SenderInterface $sender)
    {
    }

    /**
     * Dispatches a command asynchronously
     *
     * @inheritDoc
     */
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    /**
     * Sends a command message to the transport
     *
     * @inheritDoc
     */
    public function dispatch(CommandMessage $commandMessage): void
    {
        $this->sender->send(new Envelope($commandMessage));
    }
}
