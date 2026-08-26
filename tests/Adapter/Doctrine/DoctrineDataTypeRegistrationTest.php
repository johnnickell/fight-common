<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\DBAL\Types\Exception\TypeAlreadyRegistered;
use Fight\Common\Adapter\Doctrine\UuidDataType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UuidDataType::class)]
class DoctrineDataTypeRegistrationTest extends UnitTestCase
{
    public function test_that_pair_registers_independent_type_instances_under_the_stable_type_name(): void
    {
        $legacyType = new UuidDataType();
        $canonicalType = new UuidDataType();
        $pair = new DoctrineDataTypeRegistrationPair(
            'common_uuid',
            $legacyType,
            $canonicalType,
        );
        $legacyRegistry = new TypeRegistry();
        $canonicalRegistry = new TypeRegistry();

        $pair->registerLegacy($legacyRegistry);
        $pair->registerCanonical($canonicalRegistry);

        self::assertSame('common_uuid', $legacyType->getName());
        self::assertSame('common_uuid', $canonicalType->getName());
        self::assertSame('common_uuid', $legacyRegistry->lookupName($pair->legacyType()));
        self::assertSame('common_uuid', $canonicalRegistry->lookupName($pair->canonicalType()));
        self::assertSame($pair->legacyType(), $legacyRegistry->get('common_uuid'));
        self::assertSame($pair->canonicalType(), $canonicalRegistry->get('common_uuid'));
        self::assertNotSame($pair->legacyType(), $pair->canonicalType());
    }

    public function test_that_registry_rejects_one_type_instance_under_two_registration_names(): void
    {
        $registry = new TypeRegistry();
        $type = new UuidDataType();
        $registry->register('common_uuid', $type);

        $this->expectException(TypeAlreadyRegistered::class);
        $registry->register('legacy_common_uuid', $type);
    }
}
