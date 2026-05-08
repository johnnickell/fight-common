<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\ArrayQueue;
use Fight\Common\Domain\Collection\ArrayStack;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Common\Domain\Value\Basic\MbStringObject;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversFunction;

use function Fight\Common\Domain\string;
use function Fight\Common\Domain\mb_string;
use function Fight\Common\Domain\json_string;
use function Fight\Common\Domain\json_data;
use function Fight\Common\Domain\email;
use function Fight\Common\Domain\array_list;
use function Fight\Common\Domain\hash_set;
use function Fight\Common\Domain\hash_table;
use function Fight\Common\Domain\array_stack;
use function Fight\Common\Domain\array_queue;
use function Fight\Common\Domain\uri;
use function Fight\Common\Domain\url;
use function Fight\Common\Domain\uuid;

#[CoversFunction('Fight\Common\Domain\string')]
#[CoversFunction('Fight\Common\Domain\mb_string')]
#[CoversFunction('Fight\Common\Domain\json_string')]
#[CoversFunction('Fight\Common\Domain\json_data')]
#[CoversFunction('Fight\Common\Domain\email')]
#[CoversFunction('Fight\Common\Domain\array_list')]
#[CoversFunction('Fight\Common\Domain\hash_set')]
#[CoversFunction('Fight\Common\Domain\hash_table')]
#[CoversFunction('Fight\Common\Domain\array_stack')]
#[CoversFunction('Fight\Common\Domain\array_queue')]
#[CoversFunction('Fight\Common\Domain\uri')]
#[CoversFunction('Fight\Common\Domain\url')]
#[CoversFunction('Fight\Common\Domain\uuid')]
class FunctionsTest extends UnitTestCase
{
    public function test_that_string_returns_string_object_instance(): void
    {
        $result = string('hello');

        self::assertInstanceOf(StringObject::class, $result);
        self::assertSame('hello', $result->value());
    }

    public function test_that_mb_string_returns_mb_string_object_instance(): void
    {
        $result = mb_string('hello');

        self::assertInstanceOf(MbStringObject::class, $result);
        self::assertSame('hello', $result->value());
    }

    public function test_that_json_string_returns_json_object_instance(): void
    {
        $result = json_string('{"key":"value"}');

        self::assertInstanceOf(JsonObject::class, $result);
        self::assertSame('{"key":"value"}', $result->toString());
    }

    public function test_that_json_string_throws_domain_exception_for_invalid_json(): void
    {
        $this->expectException(DomainException::class);

        json_string('not json');
    }

    public function test_that_json_data_returns_json_object_instance(): void
    {
        $result = json_data(['key' => 'value']);

        self::assertInstanceOf(JsonObject::class, $result);
        self::assertSame('{"key":"value"}', $result->toString());
    }

    public function test_that_json_data_throws_domain_exception_for_non_encodable_data(): void
    {
        $this->expectException(DomainException::class);

        json_data(tmpfile());
    }

    public function test_that_uuid_returns_uuid_instance(): void
    {
        $result = uuid();

        self::assertInstanceOf(Uuid::class, $result);
        self::assertMatchesRegularExpression('/^[0-9a-f-]+$/', $result->toString());
    }

    public function test_that_uri_returns_uri_instance(): void
    {
        $result = uri('https://example.com');

        self::assertInstanceOf(Uri::class, $result);
        self::assertSame('https://example.com', $result->toString());
    }

    public function test_that_uri_throws_domain_exception_for_invalid_uri(): void
    {
        $this->expectException(DomainException::class);

        uri('not a uri');
    }

    public function test_that_url_returns_url_instance(): void
    {
        $result = url('https://example.com');

        self::assertInstanceOf(Url::class, $result);
        self::assertSame('https://example.com', $result->toString());
    }

    public function test_that_url_throws_domain_exception_for_non_http_scheme(): void
    {
        $this->expectException(DomainException::class);

        url('ftp://example.com');
    }

    public function test_that_email_returns_email_address_instance(): void
    {
        $result = email('test@example.com');

        self::assertInstanceOf(EmailAddress::class, $result);
        self::assertSame('test@example.com', $result->toString());
    }

    public function test_that_email_throws_domain_exception_for_invalid_email(): void
    {
        $this->expectException(DomainException::class);

        email('not an email');
    }

    public function test_that_array_list_returns_array_list_with_items(): void
    {
        $result = array_list(['a', 'b', 'c'], 'string');

        self::assertInstanceOf(ArrayList::class, $result);
        self::assertCount(3, $result);
        self::assertSame('a', $result->get(0));
        self::assertSame('b', $result->get(1));
        self::assertSame('c', $result->get(2));
    }

    public function test_that_array_list_without_type_accepts_mixed_items(): void
    {
        $result = array_list([1, 'a', true]);

        self::assertCount(3, $result);
    }

    public function test_that_array_list_with_empty_array_returns_empty_list(): void
    {
        $result = array_list([], 'string');

        self::assertInstanceOf(ArrayList::class, $result);
        self::assertCount(0, $result);
    }

    public function test_that_hash_set_returns_hash_set_with_items(): void
    {
        $result = hash_set(['a', 'b'], 'string');

        self::assertInstanceOf(HashSet::class, $result);
        self::assertCount(2, $result);
    }

    public function test_that_hash_set_without_type_accepts_mixed_items(): void
    {
        $result = hash_set([1, 'a', true]);

        self::assertCount(3, $result);
    }

    public function test_that_hash_table_returns_hash_table_with_entries(): void
    {
        $result = hash_table(['a' => 1, 'b' => 2], 'string', 'int');

        self::assertInstanceOf(HashTable::class, $result);
        self::assertCount(2, $result);
    }

    public function test_that_array_stack_returns_array_stack_with_items(): void
    {
        $result = array_stack(['a', 'b'], 'string');

        self::assertInstanceOf(ArrayStack::class, $result);
        self::assertCount(2, $result);
    }

    public function test_that_array_queue_returns_array_queue_with_items(): void
    {
        $result = array_queue(['a', 'b'], 'string');

        self::assertInstanceOf(ArrayQueue::class, $result);
        self::assertCount(2, $result);
    }
}
