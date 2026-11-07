<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Service;

use Fight\Common\Application\Mcp\Subscription\Data\SubscriberPage;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberSummary;
use Fight\Common\Domain\Mcp\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;

/**
 * Interface SubscriberReader
 */
interface SubscriberReader
{
    /**
     * Returns full-selection distinct counts of admitted retained observations without changing lifecycle state
     */
    public function summarize(SubscriberSelection $selection, SubscriberAccess $access): SubscriberSummary;

    /**
     * Returns a bounded live page and full-selection total without a snapshot or continuing permission
     */
    public function list(ListSubscribers $query, SubscriberAccess $access): SubscriberPage;
}
