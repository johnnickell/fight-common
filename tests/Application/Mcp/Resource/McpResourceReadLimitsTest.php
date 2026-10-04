<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpResourceReadLimits::class)]
final class McpResourceReadLimitsTest extends UnitTestCase
{
    public function test_that_defaults_and_finite_overrides_reserve_worst_case_encoding(): void
    {
        $defaults = new McpResourceReadLimits();
        self::assertSame(1048576, $defaults->maxContentBytes);
        self::assertSame(8388608, $defaults->maxResultBytes);
        $max = new McpResourceReadLimits(8388608, 67108864);
        $max->validate(McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => '', 'size' => 8388608]));
        self::assertSame(8388608, $max->maxContentBytes);
        $info = McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => '', 'size' => 1]);
        $exact = 6 + strlen(json_encode(['uri' => 'test:/a'])) + 256;
        (new McpResourceReadLimits(1, $exact))->validate($info);
        self::assertSame(1, $info->toArray()['size']);
        $this->expectException(DomainException::class);
        (new McpResourceReadLimits(1, $exact - 1))->validate($info);
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_limits_fail_at_composition(int $raw, int $encoded): void
    {
        $this->expectException(DomainException::class);
        new McpResourceReadLimits($raw, $encoded);
    }

    public static function invalidLimits(): iterable
    {
        yield [0, 1000];
        yield [-1, 1000];
        yield [8388609, 67108864];
        yield [1, 261];
        yield [1, 67108865];
    }

    public function test_that_declared_oversized_content_cannot_be_advertised_as_readable(): void
    {
        $this->expectException(DomainException::class);
        (new McpResourceReadLimits(1, 1024))->validate(McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => '', 'size' => 2]));
    }
}
