<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Repository\TransactionalUnitOfWork;

/**
 * Interface RecordingTransaction
 *
 * Exclusively owns the real commit for all three recording repositories. Serializes
 * recordings before invoking the operation; rejects caller-owned/nested transactions.
 * All repositories supplied to the recorder must participate in this same boundary.
 */
interface RecordingTransaction extends TransactionalUnitOfWork
{
}
