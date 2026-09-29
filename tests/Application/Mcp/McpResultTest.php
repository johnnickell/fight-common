<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpResult::class)]
final class McpResultTest extends UnitTestCase
{
    public function test_that_bounded_results_preserve_their_complete_shape_at_the_exact_encoded_limit(): void
    {
        $data = ['resources' => [], 'ttlMs' => 0, 'cacheScope' => 'private'];
        $plain = McpResult::complete($data);
        $bytes = strlen(json_encode($plain->toArray(), JSON_THROW_ON_ERROR));
        self::assertSame($plain->toArray(), McpResult::boundedComplete($data, $bytes)->toArray());
        $withMetadata = $plain->withMetadata(['server' => 'é']);
        $withBytes = strlen(json_encode($withMetadata->toArray(), JSON_THROW_ON_ERROR));
        self::assertSame($withMetadata->toArray(), McpResult::boundedComplete($data, $withBytes)->withMetadata(['server' => 'é'])->toArray());
        self::assertArrayNotHasKey('_meta', $plain->toArray());
    }

    #[DataProvider('invalidBudgets')]
    public function test_that_invalid_or_exceeded_budgets_reject_without_truncation(int $limit): void
    {
        $this->expectException(DomainException::class);
        McpResult::boundedComplete(['resources' => []], $limit);
    }

    public static function invalidBudgets(): iterable
    {
        yield [-1];
        yield [0];
        yield [strlen(json_encode(McpResult::complete(['resources' => []])->toArray(), JSON_THROW_ON_ERROR)) - 1];
    }

    public function test_that_bound_survives_repeated_metadata_enrichment(): void
    {
        $result = McpResult::boundedComplete(['resources' => []], 100)->withMetadata([]);
        self::assertSame([], $result->toArray()['_meta']);
        $this->expectException(DomainException::class);
        $result->withMetadata(['large' => str_repeat('x', 100)]);
    }

    public function test_that_existing_complete_results_remain_unbounded(): void
    {
        $metadata = ['large' => str_repeat('x', 2048)];
        self::assertSame($metadata, McpResult::complete([])->withMetadata($metadata)->toArray()['_meta']);
    }

    public function test_that_bounded_complete_results_cannot_override_the_result_kind(): void
    {
        $this->expectException(DomainException::class);
        McpResult::boundedComplete(['resultType' => 'input_required'], 100);
    }
}
