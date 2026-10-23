<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Execution\Detail;

use Fight\Common\Application\Execution\Detail\Data\ContentChunk;
use Fight\Common\Application\Execution\Detail\Service\DetailAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DetailSelection::class)]
#[CoversClass(GetCapturedDetail::class)]
#[CoversClass(ListCapturedMessages::class)]
#[CoversClass(ContentChunk::class)]
#[CoversClass(DetailAccess::class)]
final class ProtectedQueriesTest extends UnitTestCase
{
    public function test_that_strict_selection_queries_and_chunks_reject_invalid_unsupported_and_overflowing_input(): void
    {
        $uuid = '019a1234-5678-4abc-8def-123456789abc';
        $selection = DetailSelection::create('project', 'session', 'phase', 'producer', $uuid);
        $page = new ListCapturedMessages($selection);
        self::assertSame(1, $page->pagination()->page()); self::assertSame(32, $page->pagination()->perPage());
        self::assertSame($page->toArray(), ListCapturedMessages::fromArray($page->toArray())->toArray());
        $get = new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::TOOL_RESULT);
        self::assertSame($get->toArray(), GetCapturedDetail::fromArray($get->toArray())->toArray());
        self::assertSame(4096, $get->length()); self::assertSame(0, $get->offset());
        self::assertSame(65536, (new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, 65536, 4096))->offset());
        $invalid = [
            static fn () => DetailSelection::create('project', 'session', 'phase', 'producer', 'bad'),
            static fn () => DetailSelection::fromArray($selection->toArray() + ['authority' => true]),
            static fn () => new ListCapturedMessages($selection, 0),
            static fn () => new ListCapturedMessages($selection, 1, 0),
            static fn () => new ListCapturedMessages($selection, 1, 33),
            static fn () => new ListCapturedMessages($selection, 1000002, 1),
            static fn () => new ListCapturedMessages($selection, PHP_INT_MAX, 32),
            static fn () => new ListCapturedMessages($selection, orderings: ['body' => 'DESC']),
            static fn () => new ListCapturedMessages($selection, orderings: ['recorded_at' => 'sideways']),
            static fn () => ListCapturedMessages::fromArray(array_replace($page->toArray(), ['page' => '1'])),
            static fn () => ListCapturedMessages::fromArray(array_replace($page->toArray(), ['per_page' => false])),
            static fn () => ListCapturedMessages::fromArray(array_replace($page->toArray(), ['selection' => null])),
            static fn () => ListCapturedMessages::fromArray(array_replace($page->toArray(), ['orderings' => 'DESC'])),
            static fn () => ListCapturedMessages::fromArray($page->toArray() + ['filters' => []]),
            static fn () => GetCapturedDetail::fromArray($get->toArray() + ['body_permitted' => true]),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['selection' => null])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['detail_id' => 12])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['detail_id' => strtoupper($uuid)])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['kind' => 'hidden-reasoning'])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['kind' => false])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['offset' => 0.1])),
            static fn () => GetCapturedDetail::fromArray(array_replace($get->toArray(), ['length' => '1'])),
            static fn () => new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, -1),
            static fn () => new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, 65537),
            static fn () => new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, PHP_INT_MAX, PHP_INT_MAX),
            static fn () => new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, 0, 0),
            static fn () => new GetCapturedDetail($selection, ObservationId::fromString($uuid), DetailKind::MESSAGE, 0, 4097),
            static fn () => ContentChunk::fromBody(str_repeat('a', 65537), 0, 1),
            static fn () => ContentChunk::fromBody('a', -1, 1),
            static fn () => ContentChunk::fromBody('a', 2, 1),
            static fn () => ContentChunk::fromBody('a', 0, 0),
            static fn () => ContentChunk::fromBody('a', 0, 4097),
            static fn () => DetailAccess::create(['project' => null], metadataGrants: ['other' => null]),
            static fn () => DetailAccess::create(['project' => null], bodyGrants: ['project' => ['bad']])
        ];
        foreach ($invalid as $action) {
            try { $action(); self::fail('Malformed input must reject.'); }
            catch (DomainException $failure) { self::assertNotSame('', $failure->getMessage()); }
        }
    }

    public function test_that_byte_continuation_handles_multibyte_splits_exact_end_and_empty_without_text_loss(): void
    {
        $body = "a🙂β\r\n"; $reconstructed = ''; $offset = 0;
        do {
            $chunk = ContentChunk::fromBody($body, $offset, 2)->toArray();
            $reconstructed .= base64_decode($chunk['data'], true);
            $offset = $chunk['next_offset'];
        } while ($offset !== null);
        self::assertSame($body, $reconstructed);
        self::assertSame(['encoding' => 'base64', 'data' => '', 'offset' => 0, 'bytes' => 0, 'total_bytes' => 0, 'next_offset' => null], ContentChunk::fromBody('', 0, 4096)->toArray());
        self::assertNull(ContentChunk::fromBody('body', 4, 4096)->toArray()['next_offset']);
        $access = DetailAccess::create(['project' => null], metadataGrants: ['project' => null]);
        self::assertFalse($access->permitsBody('project', 'producer', 'any'));
        self::assertSame(['project' => null], $access->scope()->projects());
    }
}
