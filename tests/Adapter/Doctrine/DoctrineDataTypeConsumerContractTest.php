<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Adapter\Persistence\Doctrine\Type\EmailAddressDataType;
use Fight\Common\Adapter\Persistence\Doctrine\Type\JsonObjectDataType;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[CoversNothing]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
final class DoctrineDataTypeConsumerContractTest extends UnitTestCase
{
    /**
     * @param class-string<Type> $canonicalClass
     */
    #[DataProvider('types')]
    public function test_that_a_consumer_can_register_discover_and_round_trip_every_supported_type_identity(
        string $canonicalClass,
        string $typeName,
        string $storedValue,
    ): void {
        $canonicalType = new $canonicalClass();
        $registry = Type::getTypeRegistry();
        $platform = new SQLitePlatform();

        $registry->register($typeName, $canonicalType);
        $canonicalSchema = new Schema([
            new Table('consumer_canonical', [new Column('value', $canonicalType)]),
        ]);

        self::assertSame($canonicalType, $registry->get($typeName));
        self::assertSame($typeName, $registry->lookupName($canonicalType));
        self::assertSame($canonicalClass, $canonicalSchema->getTable('consumer_canonical')->getColumn('value')->getType()::class);
        $canonicalValue = $canonicalType->convertToPHPValue($storedValue, $platform);

        self::assertEquals(
            $canonicalValue,
            $canonicalType->convertToPHPValue($canonicalType->convertToDatabaseValue($canonicalValue, $platform), $platform),
        );
    }

    public function test_that_registered_email_type_preserves_quoted_address_text_and_parts(): void
    {
        $registry = Type::getTypeRegistry();
        $platform = new SQLitePlatform();
        $registry->register('common_email_address', new EmailAddressDataType());
        $registered = $registry->get('common_email_address');
        foreach ([
            ['"A\"@B"@Example.COM', 'Example.COM'],
            ['"A\"@B"@[IPv6:2001:db8::1]', 'IPv6:2001:db8::1']
        ] as [$address, $domain]) {
            $value = $registered->convertToPHPValue($address, $platform);
            self::assertInstanceOf(EmailAddress::class, $value);
            self::assertSame('"A\"@B"', $value->localPart());
            self::assertSame($domain, $value->domainPart());
            self::assertSame($address, $value->toString());
            self::assertSame($address, $registered->convertToDatabaseValue($value, $platform));
        }
    }

    public function test_that_registered_json_type_writes_snapshots_without_changing_legacy_hydration(): void
    {
        $snapshot = JsonObject::fromSnapshotString('{"empty":{},"numeric":{"0":"zero"},"float":1.0}');
        $platform = new SQLitePlatform();
        $type = new JsonObjectDataType();
        $stored = $type->convertToDatabaseValue($snapshot, $platform);
        self::assertSame('{"empty":{},"numeric":{"0":"zero"},"float":1.0}', $stored);
        $hydrated = $type->convertToPHPValue($stored, $platform);
        self::assertSame('{"empty":[],"numeric":["zero"],"float":1}', $hydrated->toString());
    }

    /**
     * @return iterable<string, array{class-string<Type>, string, string}>
     */
    public static function types(): iterable
    {
        yield 'audit entry id' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\AuditEntryIdDataType', 'audit_entry_id', '00000000-0000-4000-8000-000000000001'];
        yield 'email address' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\EmailAddressDataType', 'common_email_address', 'consumer@example.test'];
        yield 'json object' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\JsonObjectDataType', 'common_json', '{"key":"value"}'];
        yield 'multibyte string object' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringObjectDataType', 'common_mb_string', 'consumer'];
        yield 'multibyte string text' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringTextDataType', 'common_mb_string_text', 'consumer'];
        yield 'message' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MessageDataType', 'common_message', '{"@":"Fight.Common.Domain.Messaging.Command.CommandMessage","$":{"id":"00000000-0000-4000-8000-000000000001","type":"command","timestamp":0,"meta":[],"payload_type":"Fight.Test.Common.Domain.Serialization.SampleCommand","payload":{"value":"fixture"}}}'];
        yield 'metadata' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MetaDataType', 'common_meta', '{"key":"value","count":42}'];
        yield 'string object' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringObjectDataType', 'common_string', 'consumer'];
        yield 'string text' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringTextDataType', 'common_string_text', 'consumer'];
        yield 'type' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\TypeDataType', 'common_type', 'consumer'];
        yield 'uri' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UriDataType', 'common_uri', 'https://consumer.example.test/path'];
        yield 'url' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UrlDataType', 'common_url', 'https://consumer.example.test/path'];
        yield 'uuid' => ['Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UuidDataType', 'common_uuid', '00000000-0000-4000-8000-000000000001'];
    }
}
