<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Exception;

use RuntimeException;

/**
 * Class NoticeRemovalRejected
 */
final class NoticeRemovalRejected extends RuntimeException
{
    /**
     * Constructs NoticeRemovalRejected
     */
    public function __construct()
    {
        parent::__construct('Notice removal rejected.');
    }
}
