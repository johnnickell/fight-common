<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Exception;

use RuntimeException;

/**
 * Class NoticeConflict
 */
final class NoticeConflict extends RuntimeException
{
    /**
     * Constructs NoticeConflict
     */
    public function __construct()
    {
        parent::__construct('Notice content conflicts.');
    }
}
