<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\CommandHandler;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Task\Service\TaskInputLifecycle;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Task\Command\AcquireTaskInput;
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
