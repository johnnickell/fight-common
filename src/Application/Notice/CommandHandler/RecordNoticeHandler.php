<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\CommandHandler;

use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Notice\Service\NoticeRecorder;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Notice\Command\RecordNotice;
use InvalidArgumentException;

/**
 * Class RecordNoticeHandler
 */
final readonly class RecordNoticeHandler implements CommandHandler
{
    /**
     * Constructs RecordNoticeHandler
     */
    public function __construct(private NoticeRecorder $recorder)
    {
    }

    /**
     * @inheritDoc
     */
    public static function commandRegistration(): string
    {
        return RecordNotice::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();
        if (!$command instanceof RecordNotice) {
            throw new InvalidArgumentException('Unexpected notice command.');
        }

        $this->recorder->record($command->notice());
    }
}
