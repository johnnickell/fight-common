<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\QueryHandler;

use Fight\Common\Application\Mcp\Subscription\Data\SubscriberSummary;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReadAdmission;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Subscription\Query\SummarizeSubscribers;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class SummarizeSubscribersHandler
 */
final readonly class SummarizeSubscribersHandler implements QueryHandler
{
    /**
     * Constructs SummarizeSubscribersHandler
     */
    public function __construct(
        private SubscriberReadAdmission $subscriberReadAdmission,
        private SubscriberReader $subscriberReader
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return SummarizeSubscribers::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): SubscriberSummary
    {
        /**
         * @var SummarizeSubscribers $query
         */
        $query = $queryMessage->payload();
        try {
            return $this->subscriberReader->summarize(
                $query->selection(),
                $this->subscriberReadAdmission->currentAccess($query->selection())
            );
        } catch (Throwable $throwable) {
            throw new SubscriberReadFailed($throwable);
        }
    }
}
