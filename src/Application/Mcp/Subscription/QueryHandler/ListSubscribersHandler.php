<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\QueryHandler;

use Fight\Common\Application\Mcp\Subscription\Data\SubscriberPage;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReadAdmission;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReader;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Throwable;

/**
 * Class ListSubscribersHandler
 */
final readonly class ListSubscribersHandler implements QueryHandler
{
    /**
     * Constructs ListSubscribersHandler
     */
    public function __construct(private SubscriberReadAdmission $admission, private SubscriberReader $reader)
    {
    }

    /**
     * @inheritDoc
     */
    public static function queryRegistration(): string
    {
        return ListSubscribers::class;
    }

    /**
     * @inheritDoc
     */
    public function handle(QueryMessage $queryMessage): SubscriberPage
    {
        /**
         * @var ListSubscribers $query
         */
        $query = $queryMessage->payload();
        try {
            return $this->reader->list($query, $this->admission->currentAccess($query->selection()));
        } catch (Throwable $throwable) {
            throw new SubscriberReadFailed($throwable);
        }
    }
}
