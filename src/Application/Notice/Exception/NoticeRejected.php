<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Exception;

use RuntimeException;

/**
 * Class NoticeRejected
 */
final class NoticeRejected extends RuntimeException
{
    /**
     * Constructs NoticeRejected
     */
    public function __construct()
    {
        parent::__construct('Notice recording rejected.');
    }
}
