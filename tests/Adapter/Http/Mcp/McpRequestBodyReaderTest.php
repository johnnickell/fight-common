<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Mcp;

use Fight\Common\Adapter\Http\Mcp\McpRequestBodyReader;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequestLimits;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(McpRequestBodyReader::class)]
final class McpRequestBodyReaderTest extends UnitTestCase
{
    #[DataProvider('bodySizes')]
    public function test_that_actual_reads_are_bounded_without_size_metadata_or_unrestricted_conversion(int $available, int $limit, int $fragment): void
    {
        $remaining = $available;
        $consumed = 0;
        $requests = [];
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('isSeekable')->once()->andReturn(false);
        $body->shouldNotReceive('rewind', 'seek', '__toString', 'getContents', 'getSize', 'close');
        // Capture by reference: this stream produces bytes on demand, never the entire configured budget.
        $body->shouldReceive('eof')->andReturnUsing(function () use (&$remaining) { return $remaining === 0; });
        $body->shouldReceive('read')->andReturnUsing(function (int $length) use (&$remaining, &$consumed, &$requests, $fragment) {
            $requests[] = $length;
            $count = min($remaining, $length, $fragment);
            $remaining -= $count;
            $consumed += $count;
            return str_repeat('x', $count);
        });

        $result = (new McpRequestBodyReader())->read($body, new McpRequestLimits(maxBytes: $limit));
        self::assertSame(min($available, $limit + 1), $consumed);
        self::assertLessThanOrEqual(8192, max([0, ...$requests]));
        if ($available > $limit) {
            self::assertNull($result);
        } else {
            self::assertSame(str_repeat('x', $available), $result);
        }
    }

    public static function bodySizes(): iterable
    {
        yield 'empty' => [0, 10, 10];
        yield 'below limit' => [9, 10, 3];
        yield 'exact limit' => [10, 10, 3];
        yield 'one extra byte' => [11, 10, 3];
        yield 'large endless-looking body' => [PHP_INT_MAX, 16384, 8192];
        yield 'large limit tiny body' => [4, 67108864, 4];
        yield 'one-byte budget' => [2, 1, 1];
        yield 'chunk boundary' => [8192, 8192, 8192];
    }

    public function test_that_seekable_streams_restart_without_using_unbounded_string_conversion(): void
    {
        $body = (new HttpFactory())->createStream('é世界');
        $body->seek(2);
        self::assertSame('é世界', (new McpRequestBodyReader())->read($body, new McpRequestLimits(maxBytes: 8)));
        self::assertTrue($body->isReadable());
        self::assertNull((new McpRequestBodyReader())->read($body, new McpRequestLimits(maxBytes: 7)));
    }

    public function test_that_an_empty_read_may_establish_eof_at_the_exact_limit(): void
    {
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('isSeekable')->once()->andReturn(false);
        $body->shouldReceive('eof')->times(2)->andReturn(false, true);
        $body->shouldReceive('read')->with(4)->once()->andReturn('abc');
        $body->shouldReceive('read')->with(1)->once()->andReturn('');
        self::assertSame('abc', (new McpRequestBodyReader())->read($body, new McpRequestLimits(maxBytes: 3)));
    }

    #[DataProvider('brokenStreams')]
    public function test_that_broken_streams_fail_closed_instead_of_truncating_or_looping(string $stage): void
    {
        $body = $this->mock(StreamInterface::class);
        $body->shouldReceive('isSeekable')->once()->andReturn($stage === 'rewind');
        $failure = new McpProtocolException(McpProtocolError::invalidParams(), 'untrusted-id');
        if ($stage === 'rewind') {
            $body->shouldReceive('rewind')->once()->andThrow($failure);
            $body->shouldNotReceive('read');
        } elseif ($stage === 'eof') {
            $body->shouldReceive('read')->with(4)->once()->andReturn('a');
            $body->shouldReceive('eof')->once()->andThrow($failure);
        } else {
            $body->shouldReceive('eof')->andReturn(false);
            $read = $body->shouldReceive('read')->with(4)->once();
            if ($stage === 'read') {
                $read->andThrow($failure);
            } else {
                $read->andReturn($stage === 'over-return' ? 'abcde' : '');
            }
        }
        try {
            (new McpRequestBodyReader())->read($body, new McpRequestLimits(maxBytes: 3));
            self::fail('A broken stream was accepted.');
        } catch (RuntimeException $actual) {
            self::assertSame('Unable to read the MCP request body.', $actual->getMessage());
            if (in_array($stage, ['rewind', 'read', 'eof'], true)) {
                self::assertSame($failure, $actual->getPrevious());
            } else {
                self::assertInstanceOf(RuntimeException::class, $actual->getPrevious());
            }
        }
    }

    public static function brokenStreams(): iterable
    {
        foreach (['rewind', 'read', 'eof', 'over-return', 'no-progress'] as $stage) {
            yield [$stage];
        }
    }
}
