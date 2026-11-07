<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

use Fight\Common\Domain\Mcp\Notice\NoticeSelection;

/**
 * Interface NoticeReadAdmission
 */
interface NoticeReadAdmission
{
    /**
     * Returns fresh trusted whole-metadata grants for this invocation
     *
     * Consumers resolve current identity and policy independently of selection fields,
     * prior results, recording/removal grants and human or SUPER_ADMIN labels
     */
    public function currentAccess(NoticeSelection $selection): NoticeAccess;
}
