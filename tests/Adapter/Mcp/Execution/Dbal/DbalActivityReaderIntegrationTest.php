<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Execution\Dbal;

use Fight\Test\Common\TestCase\Mcp\Execution\ActivityReadTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DbalActivityReaderIntegrationTest extends ActivityReadTestCase
{
    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $backend) { yield $backend => [$backend]; }
    }
}
