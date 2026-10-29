<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Service;

use Fight\Common\Domain\Mcp\Detail\DetailSelection;

/**
 * Interface DetailReadAdmission
 */
interface DetailReadAdmission
{
    /**
     * Returns fresh consumer-owned protected metadata and body decisions for every page or chunk
     *
     * Timeline access, references and prior reads confer no authority
     */
    public function currentAccess(DetailSelection $selection): DetailAccess;
}
