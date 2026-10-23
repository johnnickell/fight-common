<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Read;

/**
 * Interface ExecutionReadAdmission
 */
interface ExecutionReadAdmission
{
    /**
     * Returns current trusted caller grants before any storage read
     *
     * Consumers own authentication, human/Agent policy and revocation synchronization.
     * The decision must remain effective throughout this invocation. Empty grants
     * conceal all records; selected IDs and message metadata confer no authority.
     */
    public function currentAccess(ExecutionSelection $selection): ExecutionAccess;
}
