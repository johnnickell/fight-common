<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\MetaDataType;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MetaDataType::class)]
class MetaDataTypeTest extends UnitTestCase
{
    private MetaDataType $type;

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new MetaDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'context'];
        $this->platform->shouldReceive('getJsonTypeDeclarationSQL')->with($column)->andReturn('JSON');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('JSON', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(MetaDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('not-a-meta-object', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_json_string(): void
    {
        $meta = Meta::create(['key' => 'value', 'count' => 42]);
        $result = $this->type->convertToDatabaseValue($meta, $this->platform);

        self::assertSame($meta->toString(), $result);
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
        $meta = Meta::create(['key' => 'value']);
        $result = $this->type->convertToPHPValue($meta, $this->platform);

        self::assertSame($meta, $result);
    }

    public function test_that_convert_to_php_value_creates_from_json_string(): void
    {
        $json = '{"key":"value","count":42}';
        $result = $this->type->convertToPHPValue($json, $this->platform);

        self::assertInstanceOf(Meta::class, $result);
        self::assertSame('value', $result->get('key'));
        self::assertSame(42, $result->get('count'));
    }

    public function test_that_convert_to_php_value_throws_for_invalid_json(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('{invalid json}', $this->platform);
    }
}
