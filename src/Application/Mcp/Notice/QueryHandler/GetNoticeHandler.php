<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\QueryHandler;

use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class GetNoticeHandler
 */
final readonly class GetNoticeHandler implements QueryHandler
{
    /**
     * Constructs GetNoticeHandler
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
        return GetNotice::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): ?NoticeView
    {
        /**
         * @var GetNotice $query
         */
        $query = $queryMessage->payload();
        try {
            $access = $this->noticeReadAdmission->currentAccess($query->selection());

            return $this->noticeReader->inspect($query, $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
