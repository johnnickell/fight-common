<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Resource;

use Fight\Common\Application\Mcp\Resource\McpResourceContent;
use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(McpResourceContent::class)]
final class McpResourceContentTest extends UnitTestCase
{
    #[DataProvider('validContent')]
    public function test_that_content_preserves_exact_bytes_and_closes_the_stream(string $bytes, bool $text): void
    {
        $info = $this->info(['size' => strlen($bytes)]);
        $stream = Utils::streamFor($bytes);
        $content = $text ? McpResourceContent::text($info, $stream) : McpResourceContent::binary($info, $stream);
        $result = $content->consume($info, new McpResourceReadLimits(max(1, strlen($bytes)), 200000));
        self::assertSame(['uri' => 'test:/a', 'mimeType' => 'text/plain', $text ? 'text' : 'blob' => $text ? $bytes : base64_encode($bytes)], $result);
        self::assertFalse($stream->isReadable());
    }

    public static function validContent(): iterable
    {
        yield ["雪 café\r\n\"\\/\0", true];
        yield [str_repeat("\0", 9000), true];
        yield ["\0\xff\x80\r\n", false];
        yield ['', true];
        yield ['', false];
    }

    public function test_that_unknown_mime_and_size_are_optional_and_metadata_order_is_irrelevant(): void
    {
        $info = McpResourceInfo::fromArray(['uri' => 'test:/a', 'name' => 'A']);
        $actual = McpResourceInfo::fromArray(['name' => 'A', 'uri' => 'test:/a']);
        self::assertSame(['uri' => 'test:/a', 'text' => 'abc'], McpResourceContent::text($actual, Utils::streamFor('abc'))->consume($info, new McpResourceReadLimits()));
    }

    #[DataProvider('mismatchedMetadata')]
    public function test_that_mismatched_metadata_fails_before_read_and_closes(array $changes): void
    {
        $body = $this->mock(StreamInterface::class);
        $body->shouldNotReceive('read');
        $body->shouldReceive('close')->once();
        $this->expectException(DomainException::class);
        McpResourceContent::text($this->info($changes), $body)->consume($this->info(), new McpResourceReadLimits());
    }

    public static function mismatchedMetadata(): iterable
    {
        yield [['uri' => 'test:/other']];
        yield [['mimeType' => 'application/octet-stream']];
        yield [['size' => 1]];
    }

    public function test_that_numeric_looking_mime_labels_do_not_compare_loosely(): void
    {
        $body = Utils::streamFor('');
        try {
            McpResourceContent::text($this->info(['mimeType' => '01']), $body)->consume($this->info(['mimeType' => '1']), new McpResourceReadLimits());
            self::fail('Different MIME strings accepted.');
        } catch (DomainException) { self::assertFalse($body->isReadable()); }
    }

    public function test_that_used_or_unreadable_streams_do_not_return_partial_content(): void
    {
        $used = Utils::streamFor('abc');
        $used->read(1);
        $closed = Utils::streamFor('abc');
        $closed->close();
        foreach ([$used, $closed] as $stream) {
            try { McpResourceContent::text($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits()); self::fail('Invalid stream accepted.'); }
            catch (DomainException) { self::assertFalse($stream->isReadable()); }
        }
    }

    #[DataProvider('invalidBytes')]
    public function test_that_invalid_text_and_size_mismatch_are_never_repaired(string $bytes, array $metadata): void
    {
        $info = $this->info($metadata);
        $body = Utils::streamFor($bytes);
        try { McpResourceContent::text($info, $body)->consume($info, new McpResourceReadLimits()); self::fail('Invalid content accepted.'); }
        catch (DomainException) { self::assertFalse($body->isReadable()); }
    }

    public static function invalidBytes(): iterable
    {
        yield ["\xff", []];
        yield ['abc', ['size' => 2]];
        yield ['', ['size' => 1]];
    }

    public function test_that_raw_overflow_reads_only_the_budget_plus_one_and_never_truncates(): void
    {
        $stream = $this->stream();
        $stream->shouldReceive('read')->once()->with(4)->andReturn('abcd');
        $stream->shouldNotReceive('getContents');
        $stream->shouldReceive('close')->once();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('raw byte budget');
        McpResourceContent::binary($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits(3, 1024));
    }

    public function test_that_streams_violating_chunk_bounds_are_rejected_even_below_the_total_budget(): void
    {
        $stream = $this->stream();
        $stream->shouldReceive('read')->once()->with(8192)->andReturn(str_repeat('a', 8193));
        $stream->shouldReceive('close')->once();
        $this->expectException(DomainException::class);
        McpResourceContent::text($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits());
    }

    public function test_that_a_stalled_stream_fails_instead_of_spinning_forever(): void
    {
        $stream = $this->stream();
        $stream->shouldReceive('read')->once()->andReturn('');
        $stream->shouldReceive('eof')->once()->andReturn(false);
        $stream->shouldReceive('close')->once();
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no progress');
        McpResourceContent::text($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits());
    }

    public function test_that_short_chunks_form_one_complete_content_item(): void
    {
        $stream = $this->stream();
        $stream->shouldReceive('read')->with(4)->once()->andReturn('a');
        $stream->shouldReceive('read')->with(3)->once()->andReturn('bc');
        $stream->shouldReceive('read')->with(1)->once()->andReturn('');
        $stream->shouldReceive('eof')->andReturn(false, false, true, true);
        $stream->shouldReceive('close')->once();
        self::assertSame('abc', McpResourceContent::text($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits(3, 1024))['text']);
    }

    public function test_that_read_failure_and_limit_failure_close_the_owned_stream(): void
    {
        $stream = $this->stream();
        $stream->shouldReceive('read')->once()->andThrow(new RuntimeException('provider read failure'));
        $stream->shouldReceive('close')->once();
        try { McpResourceContent::text($this->info(), $stream)->consume($this->info(), new McpResourceReadLimits()); self::fail('Failed stream accepted.'); }
        catch (RuntimeException $error) { self::assertSame('provider read failure', $error->getMessage()); }
        $body = Utils::streamFor('ab');
        $info = $this->info(['size' => 2]);
        try { McpResourceContent::text($info, $body)->consume($info, new McpResourceReadLimits(1, 1024)); self::fail('Invalid descriptor accepted.'); }
        catch (DomainException) { self::assertFalse($body->isReadable()); }
    }

    private function stream(): StreamInterface
    {
        $stream = $this->mock(StreamInterface::class);
        $stream->shouldReceive('isReadable')->once()->andReturn(true);
        $stream->shouldReceive('tell')->once()->andReturn(0);
        return $stream;
    }

    private function info(array $fields = []): McpResourceInfo
    {
        return McpResourceInfo::fromArray([...['uri' => 'test:/a', 'name' => 'A', 'mimeType' => 'text/plain'], ...$fields]);
    }
}
