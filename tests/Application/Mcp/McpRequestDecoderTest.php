<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp;

use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestDecoder;
use Fight\Common\Application\Mcp\McpRequestLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(McpRequestDecoder::class)]
#[CoversClass(McpRequestLimits::class)]
final class McpRequestDecoderTest extends UnitTestCase
{
    public function test_that_default_and_explicit_limits_preserve_existing_decoder_calls(): void
    {
        $decoder = new McpRequestDecoder();
        self::assertSame(1048576, $decoder->limits()->maxBytes);
        self::assertSame(512, $decoder->limits()->maxDepth);
        self::assertSame('request-é', $decoder->decode($this->json())->id());
        $limits = new McpRequestLimits(maxBytes: 67108864, maxDepth: 1);
        self::assertSame($limits, (new McpRequestDecoder($limits))->limits());
    }

    #[DataProvider('invalidLimits')]
    public function test_that_invalid_limits_fail_at_composition(int $bytes, int $depth): void
    {
        $this->expectException(DomainException::class);
        new McpRequestLimits($bytes, $depth);
    }

    public static function invalidLimits(): iterable
    {
        yield [0, 512];
        yield [-1, 512];
        yield [67108865, 512];
        yield [PHP_INT_MAX, 512];
        yield [1024, 0];
        yield [1024, -1];
        yield [1024, 513];
        yield [1024, PHP_INT_MAX];
    }

    public function test_that_encoded_bytes_include_unicode_and_whitespace_without_recovering_an_oversize_id(): void
    {
        $json = $this->json();
        $decoder = new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json)));
        self::assertSame('request-é', $decoder->decode($json)->id());
        self::assertSame('request-é', (new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json) + 1)))->decode($json)->id());
        $this->assertRejection($decoder, $json.' ', -32600);
        $this->assertRejection(new McpRequestDecoder(new McpRequestLimits(maxBytes: strlen($json) - 1)), $json, -32600);
        // A malformed oversized input must fail the byte check before parsing as well.
        $this->assertRejection($decoder, str_repeat('{', strlen($json) + 1), -32600);
    }

    #[DataProvider('depthBoundaries')]
    public function test_that_parsing_enforces_the_configured_container_depth(int $depth, int $containers, string $leaf, bool $accepted): void
    {
        // Root and params contribute two containers; metadata's own branch uses four total.
        $json = $this->json(str_repeat('[', $containers).$leaf.str_repeat(']', $containers));
        $decoder = new McpRequestDecoder(new McpRequestLimits(maxDepth: $depth));
        if ($accepted) {
            self::assertSame('request-é', $decoder->decode($json)->id());
        } else {
            $this->assertRejection($decoder, $json, -32700);
        }
    }

    public static function depthBoundaries(): iterable
    {
        yield 'metadata fits exactly' => [5, 0, '0', true];
        yield 'metadata exceeds limit' => [4, 0, '0', false];
        yield 'configured nested scalar fits' => [8, 5, '0', true];
        yield 'configured nested scalar exceeds' => [8, 6, '0', false];
        yield 'empty object at boundary' => [8, 4, '{}', true];
        yield 'empty object exceeds boundary' => [8, 5, '{}', false];
        yield 'default 511 containers' => [512, 509, '0', true];
        yield 'default 512 containers' => [512, 510, '0', false];
    }

    #[DataProvider('malformedJson')]
    public function test_that_malformed_and_unrepresentable_json_keeps_safe_parse_errors(string $json): void
    {
        $this->assertRejection(new McpRequestDecoder(), $json, -32700);
    }

    public static function malformedJson(): iterable
    {
        yield [''];
        yield ['{"id":"secret",'];
        yield ["\xff"];
        yield ['{"\\u0000private":1}'];
        yield ['{"value":1e999}'];
    }

    private function assertRejection(McpRequestDecoder $decoder, string $json, int $code): void
    {
        try {
            $decoder->decode($json);
            self::fail('Unsafe input was decoded.');
        } catch (McpProtocolException $failure) {
            self::assertNull($failure->requestId());
            self::assertSame($code, $failure->protocolError()->toArray()['code']);
        }
    }

    private function json(string $nested = '0'): string
    {
        return '{"jsonrpc":"2.0","id":"request-é","method":"example/echo","params":{"nested":'.$nested.',"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}';
    }
}
