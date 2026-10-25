<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\CommandHandler;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Notice\Service\NoticeRemover;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Notice\Command\RemoveNotices;
use InvalidArgumentException;

/**
 * Class RemoveNoticesHandler
 */
final readonly class RemoveNoticesHandler implements CommandHandler
{
    /**
     * Constructs RemoveNoticesHandler
     */
    public function __construct(private NoticeRemover $remover)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RemoveNotices::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RemoveNotices) {
            throw new InvalidArgumentException('Unexpected notice removal command.');
        }

        $this->remover->remove($command->selection());
    }
}
