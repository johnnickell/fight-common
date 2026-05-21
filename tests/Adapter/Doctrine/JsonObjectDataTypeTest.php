<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\JsonObjectDataType;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonObjectDataType::class)]
class JsonObjectDataTypeTest extends UnitTestCase
{
    private JsonObjectDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new JsonObjectDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'data'];
        $this->platform->shouldReceive('getJsonTypeDeclarationSQL')->with($column)->andReturn('JSON');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('JSON', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(JsonObjectDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('{"key":"value"}', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_json_object(): void
    {
        $obj = JsonObject::fromString('{"key":"value"}');
        $result = $this->type->convertToDatabaseValue($obj, $this->platform);

        self::assertSame('{"key":"value"}', $result);
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
        $obj = JsonObject::fromString('{"x":1}');
        $result = $this->type->convertToPHPValue($obj, $this->platform);

        self::assertSame($obj, $result);
    }

    public function test_that_convert_to_php_value_creates_json_object_from_valid_string(): void
    {
        $result = $this->type->convertToPHPValue('{"hello":"world"}', $this->platform);

        self::assertInstanceOf(JsonObject::class, $result);
    }

    public function test_that_convert_to_php_value_throws_for_invalid_json_string(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-valid-json', $this->platform);
    }
}
