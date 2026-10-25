<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\QueryHandler;

use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Notice\Service\NoticeReader;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Notice\Query\GetNotice;
use Throwable;

/**
 * Class GetNoticeHandler
 */
final readonly class GetNoticeHandler implements QueryHandler
{
    /**
     * Constructs GetNoticeHandler
     */
    public function __construct(private NoticeReadAdmission $admission, private NoticeReader $reader)
    {
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
            $access = $this->admission->currentAccess($query->selection());

            return $this->reader->inspect($query, $access);
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
