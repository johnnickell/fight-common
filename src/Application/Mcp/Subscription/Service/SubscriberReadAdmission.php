<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Service;

use Fight\Common\Domain\Subscription\SubscriberSelection;

/**
 * Interface SubscriberReadAdmission
 */
interface SubscriberReadAdmission
{
    /**
     * Returns fresh trusted complete-metadata inspection grants independently of listen or notice access
     *
     * Caller selection, roles, protocol IDs and prior pages are not credentials
     */
    public function currentAccess(SubscriberSelection $selection): SubscriberAccess;
}
