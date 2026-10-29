<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\CommandHandler;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Task\Service\TaskInputLifecycle;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Task\Command\RequestTaskInput;
use InvalidArgumentException;

/**
 * Class RequestTaskInputHandler
 */
final readonly class RequestTaskInputHandler implements CommandHandler
{
    /**
     * Constructs RequestTaskInputHandler
     */
    public function __construct(private TaskInputLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RequestTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RequestTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input command.');
        }

        $this->lifecycle->request($command);
    }
}
