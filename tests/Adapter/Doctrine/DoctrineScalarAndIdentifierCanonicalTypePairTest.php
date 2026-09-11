<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Fight\Common\Adapter\Doctrine\AuditEntryIdDataType;
use Fight\Common\Adapter\Doctrine\EmailAddressDataType;
use Fight\Common\Adapter\Doctrine\MbStringObjectDataType;
use Fight\Common\Adapter\Doctrine\MbStringTextDataType;
use Fight\Common\Adapter\Doctrine\StringObjectDataType;
use Fight\Common\Adapter\Doctrine\StringTextDataType;
use Fight\Common\Adapter\Doctrine\TypeDataType;
use Fight\Common\Adapter\Doctrine\UriDataType;
use Fight\Common\Adapter\Doctrine\UrlDataType;
use Fight\Common\Adapter\Doctrine\UuidDataType;
use Fight\Common\Adapter\Persistence\Doctrine\Type\ValueObjectDataType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class DoctrineScalarAndIdentifierCanonicalTypePairTest
 */
#[CoversClass(ValueObjectDataType::class)]
class DoctrineScalarAndIdentifierCanonicalTypePairTest extends UnitTestCase
{
    /**
     * @param string $legacyClass
     * @param string $canonicalClass
     */
    #[DataProvider('typePairs')]
    public function test_that_pair_registers_independent_type_instances_under_the_stable_type_name(
        string $legacyClass,
        string $canonicalClass,
        string $typeName
    ): void {
        $legacyType = new $legacyClass();
        $canonicalType = new $canonicalClass();
        $pair = new DoctrineDataTypeRegistrationPair($typeName, $legacyType, $canonicalType);
        $legacyRegistry = new TypeRegistry();
        $canonicalRegistry = new TypeRegistry();

        $pair->registerLegacy($legacyRegistry);
        $pair->registerCanonical($canonicalRegistry);

        self::assertSame($typeName, $legacyType->getName());
        self::assertSame($typeName, $canonicalType->getName());
        self::assertSame($legacyType, $legacyRegistry->get($typeName));
        self::assertSame($canonicalType, $canonicalRegistry->get($typeName));
    }

    /**
     * @return iterable<string, array{class-string<Type>, class-string<Type>, string}>
     */
    public static function typePairs(): iterable
    {
        yield 'audit entry id' => [AuditEntryIdDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\AuditEntryIdDataType', 'audit_entry_id'];
        yield 'email address' => [EmailAddressDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\EmailAddressDataType', 'common_email_address'];
        yield 'multibyte string object' => [MbStringObjectDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringObjectDataType', 'common_mb_string'];
        yield 'multibyte string text' => [MbStringTextDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\MbStringTextDataType', 'common_mb_string_text'];
        yield 'string object' => [StringObjectDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringObjectDataType', 'common_string'];
        yield 'string text' => [StringTextDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\StringTextDataType', 'common_string_text'];
        yield 'type' => [TypeDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\TypeDataType', 'common_type'];
        yield 'uri' => [UriDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UriDataType', 'common_uri'];
        yield 'url' => [UrlDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UrlDataType', 'common_url'];
        yield 'uuid' => [UuidDataType::class, 'Fight\\Common\\Adapter\\Persistence\\Doctrine\\Type\\UuidDataType', 'common_uuid'];
    }
}
