<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Identity\Identifier;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Common\Domain\Value\ValueObject;
use Fight\Test\Common\TestCase\Mcp\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TaskId::class)]
final class TaskIdTest extends UnitTestCase
{
    public function test_that_exact_identity_is_a_reconstructable_ordered_value_not_a_uuid_or_authority(): void
    {
        $id = TaskId::fromString(TaskFixture::ID);
        $equal = TaskId::fromString(TaskFixture::ID);
        self::assertInstanceOf(ValueObject::class, $id);
        self::assertInstanceOf(Identifier::class, $id);
        self::assertNotSame($id, $equal);
        self::assertTrue($id->equals($equal));
        self::assertTrue($id->equals($id));
        self::assertFalse($id->equals(TaskFixture::ID));
        self::assertFalse($id->equals(StrictJson::fromData(TaskFixture::ID)));
        self::assertSame(TaskFixture::ID, $id->toString());
        self::assertSame(TaskFixture::ID, (string) $id);
        self::assertSame($id->hashValue(), $equal->hashValue());
        self::assertSame('"0123456789abcdef0123456789abcdef"', json_encode($id, JSON_THROW_ON_ERROR));
        self::assertTrue($id->equals(TaskId::fromString(json_decode(json_encode($id), true))));
        self::assertSame(0, $id->compareTo($equal));
        $later = TaskId::fromString(str_repeat('f', 32));
        self::assertFalse($id->equals($later));
        self::assertSame(-1, $id->compareTo($later));
        self::assertSame(1, $later->compareTo($id));
        $generated = TaskId::generate();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $generated->toString());
        self::assertTrue($generated->equals(TaskId::fromString($generated->toString())));

        $this->expectException(DomainException::class);
        $id->compareTo(StrictJson::fromData(TaskFixture::ID));
    }

    public function test_that_real_typed_collections_preserve_their_hash_and_strict_membership_contracts(): void
    {
        $first = TaskFixture::id();
        $equal = TaskFixture::id();
        $table = HashTable::of(TaskId::class, 'string');
        $table->set($first, 'first');
        $table->set($equal, 'replacement');
        self::assertCount(1, $table);
        self::assertSame('replacement', $table->get(TaskFixture::id()));
        $list = ArrayList::of(TaskId::class);
        $list->add($first);
        self::assertTrue($list->contains($first));
        self::assertFalse($list->contains($equal));
        self::assertTrue($list->contains($equal, false));
        self::assertFalse($list->contains(TaskFixture::id(str_repeat('f', 32))));
    }

    #[DataProvider('invalidIdentities')]
    public function test_that_invalid_identity_bytes_are_rejected_without_normalization(string $value): void
    {
        $this->expectException(DomainException::class);
        TaskId::fromString($value);
    }

    public static function invalidIdentities(): iterable
    {
        foreach (['', 'guessed', str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), ' '.TaskFixture::ID, TaskFixture::ID."\n", str_repeat('g', 32), '01234567-89ab-cdef-0123-456789abcdef', str_repeat('é', 16)] as $value) {
            yield [$value];
        }
    }
}
