<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Application\Notice\Data\NoticePage;
use Fight\Common\Application\Notice\Data\NoticeSummary;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\GetNotice;
use Fight\Common\Domain\Notice\Query\ListNotices;

/**
 * Interface NoticeReader
 */
interface NoticeReader
{
    /**
     * Returns full-selection currently admitted statistics without concealed totals
     */
    public function summarize(NoticeSelection $selection, NoticeAccess $access): NoticeSummary;

    /**
     * Returns a bounded live page and full admitted matching total
     */
    public function list(ListNotices $query, NoticeAccess $access): NoticePage;

    /**
     * Returns current permitted detail or identical null for denied and unknown identity
     */
    public function inspect(GetNotice $query, NoticeAccess $access): ?NoticeView;
}
