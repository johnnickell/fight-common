<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\QueryHandler;

use Fight\Common\Application\Mcp\Notice\Data\NoticePage;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Notice\Query\ListNotices;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class ListNoticesHandler
 */
final readonly class ListNoticesHandler implements QueryHandler
{
    /**
     * Constructs ListNoticesHandler
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
        return ListNotices::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): NoticePage
    {
        /**
         * @var ListNotices $query
         */
        $query = $queryMessage->payload();
        try {
            $access = $this->noticeReadAdmission->currentAccess($query->selection());

            return $this->noticeReader->list($query, $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
