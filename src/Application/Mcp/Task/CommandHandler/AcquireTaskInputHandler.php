<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskInputLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class AcquireTaskInputHandler
 */
final readonly class AcquireTaskInputHandler implements CommandHandler
{
    /**
     * Constructs AcquireTaskInputHandler
     */
    public function __construct(private TaskInputLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return AcquireTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof AcquireTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input command.');
        }

        $this->lifecycle->acquire($command);
    }
}
