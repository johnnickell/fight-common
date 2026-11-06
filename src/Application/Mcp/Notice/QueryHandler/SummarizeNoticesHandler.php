<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\QueryHandler;

use Fight\Common\Application\Mcp\Notice\Data\NoticeSummary;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Notice\Query\SummarizeNotices;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class SummarizeNoticesHandler
 */
final readonly class SummarizeNoticesHandler implements QueryHandler
{
    /**
     * Constructs SummarizeNoticesHandler
     */
    public function __construct(
        private NoticeReadAdmission $noticeReadAdmission,
        private NoticeReader $noticeReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return SummarizeNotices::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): NoticeSummary
    {
        /**
         * @var SummarizeNotices $query
         */
        $query = $queryMessage->payload();
        try {
            $access = $this->noticeReadAdmission->currentAccess($query->selection());

            return $this->noticeReader->summarize($query->selection(), $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
