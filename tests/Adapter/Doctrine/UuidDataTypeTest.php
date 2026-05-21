<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\UuidDataType;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UuidDataType::class)]
class UuidDataTypeTest extends UnitTestCase
{
    private UuidDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new UuidDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'id'];
        $this->platform->shouldReceive('getGuidTypeDeclarationSQL')->with($column)->andReturn('GUID');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('GUID', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(UuidDataType::TYPE_NAME, $this->type->getName());
    }

    public function test_that_convert_to_database_value_returns_null_for_null(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function test_that_convert_to_database_value_returns_null_for_empty_string(): void
    {
        self::assertNull($this->type->convertToDatabaseValue('', $this->platform));
    }

    public function test_that_convert_to_database_value_throws_for_wrong_type(): void
    {
        $this->expectException(InvalidType::class);
        $this->type->convertToDatabaseValue('not-a-uuid-object', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_uuid(): void
    {
        $uuid = Uuid::random();
        $result = $this->type->convertToDatabaseValue($uuid, $this->platform);

        self::assertSame($uuid->toString(), $result);
    }

    public function test_that_convert_to_php_value_returns_null_for_null(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function test_that_convert_to_php_value_returns_null_for_empty_string(): void
    {
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function test_that_convert_to_php_value_returns_same_uuid_instance(): void
    {
        $uuid = Uuid::random();
        $result = $this->type->convertToPHPValue($uuid, $this->platform);

        self::assertSame($uuid, $result);
    }

    public function test_that_convert_to_php_value_creates_uuid_from_valid_string(): void
    {
        $uuid = Uuid::random();
        $result = $this->type->convertToPHPValue($uuid->toString(), $this->platform);

        self::assertInstanceOf(Uuid::class, $result);
        self::assertSame($uuid->toString(), $result->toString());
    }

    public function test_that_convert_to_php_value_throws_for_invalid_uuid_string(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-a-valid-uuid', $this->platform);
    }
}
