<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Session\Dbal;

use Fight\Test\Common\TestCase\Execution\ExecutionReadTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DbalExecutionReaderIntegrationTest extends ExecutionReadTestCase
{
    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }
}
