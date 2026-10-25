<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Exception;

use RuntimeException;
use Throwable;

/**
 * Class NoticeReadFailed
 */
final class NoticeReadFailed extends RuntimeException
{
    /**
     * Constructs NoticeReadFailed
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Retained notices could not be read completely.', 0, $previous);
    }
}
