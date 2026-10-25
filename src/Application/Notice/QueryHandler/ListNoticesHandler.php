<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\QueryHandler;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Notice\Data\NoticePage;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Notice\Service\NoticeReader;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Notice\Query\ListNotices;
use Throwable;

/**
 * Class ListNoticesHandler
 */
final readonly class ListNoticesHandler implements QueryHandler
{
    /**
     * Constructs ListNoticesHandler
     */
    public function __construct(private NoticeReadAdmission $admission, private NoticeReader $reader)
    {
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
            $access = $this->admission->currentAccess($query->selection());

            return $this->reader->list($query, $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
