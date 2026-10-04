<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Skill;

use Fight\Common\Application\Mcp\Skill\McpSkillLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpSkillLimits::class)]
final class McpSkillLimitsTest extends UnitTestCase
{
    public function test_that_defaults_support_the_pinned_static_revision_envelope(): void
    {
        $limits = new McpSkillLimits();
        self::assertSame([512, 16777216, 65536, 32, 4194304], [$limits->maxFiles, $limits->maxTotalBytes, $limits->maxFrontmatterBytes, $limits->maxDepth, $limits->maxEntryBytes]);
        self::assertSame(1, (new McpSkillLimits(1, 1, 1, 1, 1))->maxFiles);
        self::assertSame(64, (new McpSkillLimits(maxFrontmatterBytes: 1048576, maxDepth: 64, maxEntryBytes: 16777216))->maxDepth);
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_limit_overrides_fail_before_use(array $arguments): void
    {
        $this->expectException(DomainException::class);
        new McpSkillLimits(...$arguments);
    }

    public static function invalidLimits(): iterable
    {
        foreach (['maxFiles' => [0, 513], 'maxTotalBytes' => [0, 16777217], 'maxFrontmatterBytes' => [0, 1048577], 'maxDepth' => [0, 65], 'maxEntryBytes' => [65535, 16777217]] as $key => $values) {
            foreach ($values as $value) { yield [[$key => $value]]; }
        }
    }
}
