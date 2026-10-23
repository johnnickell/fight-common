<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Fight\Test\Common\TestCase\Execution\ExecutionInspectionTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DbalExecutionInspectionIntegrationTest extends ExecutionInspectionTestCase
{
    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }
}
