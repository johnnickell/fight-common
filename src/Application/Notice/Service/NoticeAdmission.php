<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Domain\Notice\Notice;

/**
 * Interface NoticeAdmission
 */
interface NoticeAdmission
{
    /**
     * Validates current trusted producer, scope, safe content and every permitted reference
     *
     * Invoked before identity lookup on originals and retries under the storage lock
     * Implementations use trusted composition context, never submitted IDs as credentials
     * Throw NoticeRejected for denial without revealing concealed identity or content
     */
    public function admit(Notice $notice): void;
}
