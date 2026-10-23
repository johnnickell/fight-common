<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\CommandHandler;

use Fight\Common\Application\Execution\Detail\Service\DetailRemover;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Execution\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use InvalidArgumentException;

/**
 * Class RemoveCapturedDetailHandler
 */
final readonly class RemoveCapturedDetailHandler implements CommandHandler
{
    /**
     * Constructs RemoveCapturedDetailHandler
     */
    public function __construct(private DetailRemover $remover)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RemoveCapturedDetail::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RemoveCapturedDetail) {
            throw new InvalidArgumentException('Unexpected detail removal command.');
        }

        $this->remover->remove($command);
    }
}
