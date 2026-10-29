<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Publication;

use Fight\Common\Application\Mcp\Notice\Data\NoticeView;

/**
 * Interface NoticePublicationPolicy
 */
interface NoticePublicationPolicy
{
    /**
     * Returns a fresh trusted whole-audience disclosure grant or null denial for this attempt
     *
     * Read access, recipients and role labels never constitute this independent grant
     * Consumers own caller context, current audience policy and safe source metadata
     */
    public function currentGrant(NoticeView $notice): ?NoticePublicationGrant;
}
