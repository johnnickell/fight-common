<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskInputLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
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
