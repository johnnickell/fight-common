<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Publication;

use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;

/**
 * Class RecordNoticeAndPublish
 *
 * Explicit optional synchronous composition, not a new business Command or an outbox
 */
final readonly class RecordNoticeAndPublish
{
    /**
     * Constructs RecordNoticeAndPublish
     */
    public function __construct(private CommandBus $commands, private NoticePublication $publication)
    {
    }

    /**
     * Records through the actual-commit handler before attempting independently authorized publication
     *
     * Use the synchronous RecordNoticeHandler, not queued dispatch or a caller-owned transaction
     * Recording exceptions remain distinct; returned statuses describe only the later publication attempt
     * Identical recording retries deduplicate storage but deliberately repeat current-policy publication
     */
    public function record(RecordNotice $command): NoticePublicationStatus
    {
        $this->commands->execute($command);
        $notice = $command->notice();

        return $this->publication->publish(new GetNotice($notice->scope(), $notice->producer(), $notice->id()));
    }
}
