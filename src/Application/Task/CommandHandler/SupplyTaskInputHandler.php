<?php

declare(strict_types=1);

namespace Fight\Common\Application\Task\CommandHandler;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Task\Service\TaskInputLifecycle;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Task\Command\SupplyTaskInput;
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
