<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Exception;

use RuntimeException;
use Throwable;

/**
 * Class DbalTransactionOutcomeUncertain
 *
 * Reports an unconfirmed begin, commit or rollback without claiming data absence
 */
final class DbalTransactionOutcomeUncertain extends RuntimeException
{
    /**
     * Constructs DbalTransactionOutcomeUncertain
     */
    public function __construct(private readonly Throwable $originalFailure)
    {
        parent::__construct('DBAL transaction outcome is uncertain.', 0, $originalFailure);
    }

    /**
     * Returns the original failure before any best-effort cleanup
     */
    public function originalFailure(): Throwable
    {
        return $this->originalFailure;
    }
}
