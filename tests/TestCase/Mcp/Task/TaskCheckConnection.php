<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Task;

use Closure;
use Doctrine\DBAL\Connection;

/** Real driver connection with test-owned commit-boundary failure/barrier injection. */
final class TaskCheckConnection extends Connection
{
    public ?Closure $beforeCommit = null;
    public ?Closure $afterCommit = null;

    public function commit(): void
    {
        ($this->beforeCommit ?? static fn () => null)();
        parent::commit();
        ($this->afterCommit ?? static fn () => null)();
    }
}
