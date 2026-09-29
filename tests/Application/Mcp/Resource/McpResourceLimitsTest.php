<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpResourceLimits::class)]
final class McpResourceLimitsTest extends UnitTestCase
{
    public function test_that_defaults_and_consistent_boundary_overrides_are_retained(): void
    {
        self::assertSame([4096, 16384, 100, 10000, 32, 1048576], array_values(get_object_vars(new McpResourceLimits())));
        $limits = new McpResourceLimits(1, 33, 1, 1, 1, 289);
        self::assertSame([1, 33, 1, 1, 1, 289], array_values(get_object_vars($limits)));
        $maximum = new McpResourceLimits(1048576, 16777216, 1000, 1000000, 256, 67108864);
        self::assertSame(67108864, $maximum->maxResultBytes);
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_or_inconsistent_budgets_fail(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpResourceLimits(...$arguments);
    }

    public static function invalidLimits(): iterable
    {
        foreach (['maxUriBytes' => [0, 1048577], 'maxDescriptorBytes' => [4127, 16777217],
            'pageSize' => [0, 1001], 'maxDescriptors' => [99, 1000001], 'maxProviders' => [0, 257],
            'maxResultBytes' => [16639, 67108865]] as $name => $values) {
            foreach ($values as $value) { yield $name.'-'.$value => [[$name => $value]]; }
        }
    }
}
