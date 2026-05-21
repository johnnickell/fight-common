<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\TypeDataType;
use Fight\Common\Domain\Type\Type as SystemType;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(TypeDataType::class)]
class TypeDataTypeTest extends UnitTestCase
{
    private TypeDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new TypeDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'type'];
        $this->platform->shouldReceive('getStringTypeDeclarationSQL')->with($column)->andReturn('VARCHAR(255)');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('VARCHAR(255)', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(TypeDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue(new stdClass(), $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_system_type(): void
    {
        $systemType = SystemType::create(stdClass::class);
        $result = $this->type->convertToDatabaseValue($systemType, $this->platform);

        self::assertSame($systemType->toString(), $result);
    }

    public function test_that_convert_to_php_value_returns_null_for_null(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function test_that_convert_to_php_value_returns_null_for_empty_string(): void
    {
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function test_that_convert_to_php_value_returns_same_instance(): void
    {
        $systemType = SystemType::create(stdClass::class);
        $result = $this->type->convertToPHPValue($systemType, $this->platform);

        self::assertSame($systemType, $result);
    }

    public function test_that_convert_to_php_value_creates_system_type_from_string(): void
    {
        $result = $this->type->convertToPHPValue('stdClass', $this->platform);

        self::assertInstanceOf(SystemType::class, $result);
    }

    public function test_that_convert_to_php_value_throws_for_non_string_or_object_value(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue([1, 2, 3], $this->platform);
    }
}
