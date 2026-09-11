<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Fight\Common\Adapter\Doctrine\JsonObjectDataType;
use Fight\Common\Adapter\Doctrine\MessageDataType;
use Fight\Common\Adapter\Doctrine\MetaDataType;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversNothing]
class DoctrineStructuredAndMessageCanonicalTypePairTest extends UnitTestCase
{
    /**
     * @param class-string<Type> $legacyClass
     * @param class-string<Type> $canonicalClass
     */
    #[DataProvider('typePairs')]
    public function test_that_structured_and_message_pairs_preserve_public_type_identity_and_json_conversion(
        string $legacyClass,
        string $canonicalClass,
        string $typeName,
        string $storedValue,
        string $expectedDatabaseValue,
        string $expectedPhpClass,
        array $sqlColumn,
    ): void {
        $legacyType = new $legacyClass();
        $canonicalType = new $canonicalClass();
        $pair = new DoctrineDataTypeRegistrationPair($typeName, $legacyType, $canonicalType);
        $legacyRegistry = new TypeRegistry();
        $canonicalRegistry = new TypeRegistry();
        $platform = $this->mock(AbstractPlatform::class);

        $pair->registerLegacy($legacyRegistry);
        $pair->registerCanonical($canonicalRegistry);

        self::assertSame($typeName, $legacyType->getName());
        self::assertSame($typeName, $canonicalType->getName());
        self::assertSame($legacyType, $legacyRegistry->get($typeName));
        self::assertSame($canonicalType, $canonicalRegistry->get($typeName));
        $platform->shouldReceive('getJsonTypeDeclarationSQL')->twice()->with($sqlColumn)->andReturn('JSON');
        self::assertSame('JSON', $legacyType->getSQLDeclaration(['name' => 'value'], $platform));
        self::assertSame('JSON', $canonicalType->getSQLDeclaration(['name' => 'value'], $platform));
        self::assertSame(ParameterType::STRING, $legacyType->getBindingType());
        self::assertSame(ParameterType::STRING, $canonicalType->getBindingType());
        self::assertNull($legacyType->convertToPHPValue(null, $platform));
        self::assertNull($canonicalType->convertToPHPValue('', $platform));

        $legacyValue = $legacyType->convertToPHPValue($storedValue, $platform);
        $canonicalValue = $canonicalType->convertToPHPValue($storedValue, $platform);

        self::assertInstanceOf($expectedPhpClass, $legacyValue);
        self::assertInstanceOf($expectedPhpClass, $canonicalValue);
        self::assertSame($expectedDatabaseValue, $legacyType->convertToDatabaseValue($legacyValue, $platform));
        self::assertSame($expectedDatabaseValue, $canonicalType->convertToDatabaseValue($canonicalValue, $platform));
    }

    /**
     * @param class-string<Type> $legacyClass
     * @param class-string<Type> $canonicalClass
     */
    #[DataProvider('typePairs')]
    public function test_that_structured_and_message_pairs_reject_invalid_database_and_php_values(
        string $legacyClass,
        string $canonicalClass,
        string $typeName,
        string $storedValue,
        string $expectedDatabaseValue,
        string $expectedPhpClass,
        array $sqlColumn,
    ): void {
        $platform = $this->mock(AbstractPlatform::class);

        foreach ([new $legacyClass(), new $canonicalClass()] as $type) {
            try {
                $type->convertToDatabaseValue('not-a-value-object', $platform);
                self::fail('Expected an invalid PHP value to be rejected.');
            } catch (InvalidType) {
                self::assertTrue(true);
            }

            try {
                $type->convertToPHPValue('not-valid-json-at-all', $platform);
                self::fail('Expected an invalid stored value to be rejected.');
            } catch (ValueNotConvertible) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<Type>, class-string<Type>, string, string, string, class-string, array<string, int|string>}>
     */
    public static function typePairs(): iterable
    {
        yield 'json object' => [JsonObjectDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\JsonObjectDataType', 'common_json', '{"key":"value"}', '{"key":"value"}', JsonObject::class, ['name' => 'value']];
        yield 'metadata' => [MetaDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MetaDataType', 'common_meta', '{"key":"value","count":42}', '{"key":"value","count":42}', 'Fight\\Common\\Domain\\Messaging\\Meta', ['name' => 'value']];
        yield 'message' => [MessageDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MessageDataType', 'common_message', '{"@":"Fight.Common.Domain.Messaging.Command.CommandMessage","$":{"id":"00000000-0000-4000-8000-000000000001","type":"command","timestamp":0,"meta":[],"payload_type":"Fight.Test.Common.Domain.Serialization.SampleCommand","payload":{"value":"fixture"}}}', '{"@":"Fight.Common.Domain.Messaging.Command.CommandMessage","$":{"id":"00000000-0000-4000-8000-000000000001","type":"command","timestamp":"0.000000","payload_type":"Fight.Test.Common.Domain.Serialization.SampleCommand","payload":{"value":"fixture"},"meta":[]}}', 'Fight\\Common\\Domain\\Messaging\\Message', ['name' => 'value', 'length' => 4294967295]];
    }
}
