<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\QueryHandler;

use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Mcp\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Mcp\Notice\Service\NoticeRecovery;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class RecoverNoticesHandler
 */
final readonly class RecoverNoticesHandler implements QueryHandler
{
    /**
     * Constructs RecoverNoticesHandler
     */
    public function __construct(private NoticeReadAdmission $admission, private NoticeRecovery $recovery)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return RecoverNotices::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): NoticeRecoveryBatch
    {
        /**
         * @var RecoverNotices $query
         */
        $query = $queryMessage->payload();
        try {
            return $this->recovery->recover($query, $this->admission->currentAccess($query->selection()));
        } catch (Throwable $throwable) {
            throw new NoticeReadFailed($throwable);
        }
    }
}
