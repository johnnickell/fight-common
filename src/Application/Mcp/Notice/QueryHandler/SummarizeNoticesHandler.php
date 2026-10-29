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
