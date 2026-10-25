<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\QueryHandler;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Notice\Data\NoticeSummary;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Notice\Service\NoticeReader;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Notice\Query\SummarizeNotices;
use Throwable;

/**
 * Class SummarizeNoticesHandler
 */
final readonly class SummarizeNoticesHandler implements QueryHandler
{
    /**
     * Constructs SummarizeNoticesHandler
     */
    public function __construct(private NoticeReadAdmission $admission, private NoticeReader $reader)
    {
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
            $access = $this->admission->currentAccess($query->selection());

            return $this->reader->summarize($query->selection(), $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
