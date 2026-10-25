<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;

/**
 * Interface NoticeRemovalAdmission
 */
interface NoticeRemovalAdmission
{
    /**
     * Validates current removal authority for every target before any identity lookup
     *
     * Runs under the storage lock on every call including repeats and missing targets
     * Read or recording grants, IDs, labels and cutoffs are not removal authority
     * Throw NoticeRemovalRejected on denial without exposing concealed records
     */
    public function admit(NoticeRemovalSelection $selection): void;

    /**
     * Validates current removal authority for all retained content and relationships before any deletion
     *
     * Implementations use trusted current context independent of recording admission
     * Throw NoticeRemovalRejected on denial; no selected target is then removed
     */
    public function admitRetained(Notice $notice): void;
}
