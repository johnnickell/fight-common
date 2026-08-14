<?php

declare(strict_types=1);

namespace Prototype\Contract;

/** PROTOTYPE — candidate additive 1.x transaction-only port. */
interface TransactionalUnitOfWork
{
    public function commitTransactional(callable $operation): mixed;

    public function isClosed(): bool;
}

/** PROTOTYPE — candidate legacy 1.x contract, retained for Doctrine consumers. */
interface UnitOfWork extends TransactionalUnitOfWork
{
    public function commit(): void;
}
