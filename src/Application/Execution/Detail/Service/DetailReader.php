<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\Service;

use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Execution\Detail\Data\MessagePage;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;

/**
 * Interface DetailReader
 */
interface DetailReader
{
    /**
     * Returns a live bounded message page after intersecting current protected metadata and body grants
     */
    public function messages(ListCapturedMessages $query, DetailAccess $access): ?MessagePage;

    /**
     * Returns selected protected detail or the same null for concealed, missing or mismatched references
     */
    public function get(GetCapturedDetail $query, DetailAccess $access): ?CapturedDetail;
}
