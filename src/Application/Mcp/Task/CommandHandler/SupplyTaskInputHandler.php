<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskInputLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class SupplyTaskInputHandler
 */
final readonly class SupplyTaskInputHandler implements CommandHandler
{
    /**
     * Constructs SupplyTaskInputHandler
     */
    public function __construct(private TaskInputLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return SupplyTaskInput::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof SupplyTaskInput) {
            throw new InvalidArgumentException('Unexpected Task input command.');
        }

        $this->lifecycle->supply($command);
    }
}
