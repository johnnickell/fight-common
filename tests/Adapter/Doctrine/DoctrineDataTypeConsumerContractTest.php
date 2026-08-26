<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
final class DoctrineDataTypeConsumerContractTest extends UnitTestCase
{
    /**
     * @param class-string<Type> $legacyClass
     * @param class-string<Type> $canonicalClass
     */
    #[DataProvider('typePairs')]
    public function test_that_a_consumer_can_register_discover_and_round_trip_every_supported_type_identity(
        string $legacyClass,
        string $canonicalClass,
        string $typeName,
        string $storedValue,
    ): void {
        $legacyType = new $legacyClass();
        $canonicalType = new $canonicalClass();
        $legacyRegistry = new TypeRegistry();
        $canonicalRegistry = new TypeRegistry();
        $platform = new SQLitePlatform();
        $schema = new Schema([
            new Table('consumer_legacy', [new Column('value', $legacyType)]),
            new Table('consumer_canonical', [new Column('value', $canonicalType)]),
        ]);

        $legacyRegistry->register($typeName, $legacyType);
        $canonicalRegistry->register($typeName, $canonicalType);

        self::assertSame($legacyType, $legacyRegistry->get($typeName));
        self::assertSame($canonicalType, $canonicalRegistry->get($typeName));
        self::assertSame($typeName, $legacyRegistry->lookupName($legacyType));
        self::assertSame($typeName, $canonicalRegistry->lookupName($canonicalType));
        self::assertSame($legacyClass, $schema->getTable('consumer_legacy')->getColumn('value')->getType()::class);
        self::assertSame($canonicalClass, $schema->getTable('consumer_canonical')->getColumn('value')->getType()::class);

        $legacyValue = $legacyType->convertToPHPValue($storedValue, $platform);
        $canonicalValue = $canonicalType->convertToPHPValue($storedValue, $platform);

        self::assertSame(
            $legacyType->convertToDatabaseValue($legacyValue, $platform),
            $canonicalType->convertToDatabaseValue($canonicalValue, $platform),
        );
    }

    public function test_that_consumer_configuration_documents_the_canonical_paths_and_legacy_1_x_identity_policy(): void
    {
        $documentation = [
            file_get_contents(dirname(__DIR__, 3).'/docs/README.md'),
            file_get_contents(dirname(__DIR__, 3).'/docs/quickstart.md'),
            file_get_contents(dirname(__DIR__, 3).'/docs/values.md'),
        ];

        foreach ($documentation as $guide) {
            self::assertIsString($guide);
            self::assertStringContainsString('Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UuidDataType', $guide);
            self::assertStringContainsString('silent deprecated 1.x identities', preg_replace('/\\s+/', ' ', $guide) ?? '');
        }
    }

    /**
     * @return iterable<string, array{class-string<Type>, class-string<Type>, string, string}>
     */
    public static function typePairs(): iterable
    {
        yield 'audit entry id' => ['Fight\\Common\\Adapter\\Doctrine\\AuditEntryIdDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\AuditEntryIdDataType', 'audit_entry_id', '00000000-0000-4000-8000-000000000001'];
        yield 'email address' => ['Fight\\Common\\Adapter\\Doctrine\\EmailAddressDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\EmailAddressDataType', 'common_email_address', 'consumer@example.test'];
        yield 'json object' => ['Fight\\Common\\Adapter\\Doctrine\\JsonObjectDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\JsonObjectDataType', 'common_json', '{"key":"value"}'];
        yield 'multibyte string object' => ['Fight\\Common\\Adapter\\Doctrine\\MbStringObjectDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringObjectDataType', 'common_mb_string', 'consumer'];
        yield 'multibyte string text' => ['Fight\\Common\\Adapter\\Doctrine\\MbStringTextDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringTextDataType', 'common_mb_string_text', 'consumer'];
        yield 'message' => ['Fight\\Common\\Adapter\\Doctrine\\MessageDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MessageDataType', 'common_message', '{"@":"Fight.Common.Domain.Messaging.Command.CommandMessage","$":{"id":"00000000-0000-4000-8000-000000000001","type":"command","timestamp":0,"meta":[],"payload_type":"Fight.Test.Common.Domain.Serialization.SampleCommand","payload":{"value":"fixture"}}}'];
        yield 'metadata' => ['Fight\\Common\\Adapter\\Doctrine\\MetaDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MetaDataType', 'common_meta', '{"key":"value","count":42}'];
        yield 'string object' => ['Fight\\Common\\Adapter\\Doctrine\\StringObjectDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringObjectDataType', 'common_string', 'consumer'];
        yield 'string text' => ['Fight\\Common\\Adapter\\Doctrine\\StringTextDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringTextDataType', 'common_string_text', 'consumer'];
        yield 'type' => ['Fight\\Common\\Adapter\\Doctrine\\TypeDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\TypeDataType', 'common_type', 'consumer'];
        yield 'uri' => ['Fight\\Common\\Adapter\\Doctrine\\UriDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UriDataType', 'common_uri', 'https://consumer.example.test/path'];
        yield 'url' => ['Fight\\Common\\Adapter\\Doctrine\\UrlDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UrlDataType', 'common_url', 'https://consumer.example.test/path'];
        yield 'uuid' => ['Fight\\Common\\Adapter\\Doctrine\\UuidDataType', 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UuidDataType', 'common_uuid', '00000000-0000-4000-8000-000000000001'];
    }
}
