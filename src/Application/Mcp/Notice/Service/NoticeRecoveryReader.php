<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Service;

use Fight\Common\Application\Mcp\Notice\Data\NoticeRecoveryWindow;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;

/**
 * Interface NoticeRecoveryReader
 *
 * Trusted bounded feed reconstruction, not authorized live inspection
 */
interface NoticeRecoveryReader
{
    /**
     * Returns bounded rows and global loss evidence under one coherent serialization boundary
     *
     * Owns the required coherent read transaction and lock without caller sequencing
     * Unavailable evidence fails closed; global counters must not disclose concealed activity
     *
     * @param string|list<string> $scope
     */
    public function readWindow(string|array $scope, int $position, int $limit): NoticeRecoveryWindow;

    /**
     * Returns at most 100 retained notices after an exclusive position in ascending committed order
     *
     * Requires an idle connection; live rows alone neither authorize disclosure nor prove continuity
     *
     * @return list<RecordedNotice>
     */
    public function readAfter(string $scope, int $position, int $limit): array;
}
