<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\CodeIgniter;

use CodeIgniter\Queue\Interfaces\QueueInterface;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\MessageType;
use RuntimeException;

/**
 * Class QueueCommandBus
 */
final readonly class QueueCommandBus implements AsynchronousCommandBus
{
    /**
     * Constructs QueueCommandBus
     */
    public function __construct(
        private QueueInterface $queue,
        private string $queueName,
        private string $jobAlias
    ) {
    }

    /**
     * Executes one command in a new complete envelope
     */
    public function execute(Command $command): void
    {
        $this->dispatch(CommandMessage::create($command));
    }

    /**
     * Dispatches one complete command envelope
     */
    public function dispatch(CommandMessage $commandMessage): void
    {
        $result = $this->queue->push(
            $this->queueName,
            $this->jobAlias,
            [
                'kind'    => MessageType::COMMAND->value,
                'message' => $commandMessage->arraySerialize()
            ]
        );

        if (!$result->getStatus()) {
            throw new RuntimeException(sprintf(
                'CodeIgniter Queue could not submit command message: %s',
                $result->getError() ?? 'unknown queue failure'
            ));
        }
    }
}
