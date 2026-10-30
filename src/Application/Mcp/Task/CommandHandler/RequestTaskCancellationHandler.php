<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\CommandHandler;

use Fight\Common\Application\Mcp\Task\Service\TaskCancellationLifecycle;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RequestTaskCancellationHandler
 */
final readonly class RequestTaskCancellationHandler implements CommandHandler
{
    /**
     * Constructs RequestTaskCancellationHandler
     */
    public function __construct(private TaskCancellationLifecycle $lifecycle)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RequestTaskCancellation::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RequestTaskCancellation) {
            throw new InvalidArgumentException('Unexpected Task cancellation command.');
        }

        $this->lifecycle->request($command);
    }
}
