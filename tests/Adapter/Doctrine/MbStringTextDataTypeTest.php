<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\MbStringTextDataType;
use Fight\Common\Domain\Value\Basic\MbStringObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MbStringTextDataType::class)]
class MbStringTextDataTypeTest extends UnitTestCase
{
    private MbStringTextDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new MbStringTextDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'content'];
        $this->platform->shouldReceive('getClobTypeDeclarationSQL')->with($column)->andReturn('LONGTEXT');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('LONGTEXT', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(MbStringTextDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('plain string', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_mb_string_object(): void
    {
        $obj = MbStringObject::fromString('long multibyte content');
        $result = $this->type->convertToDatabaseValue($obj, $this->platform);

        self::assertSame('long multibyte content', $result);
    }

    public function test_that_convert_to_php_value_returns_null_for_null(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function test_that_convert_to_php_value_returns_mb_string_object_for_empty_string(): void
    {
        $result = $this->type->convertToPHPValue('', $this->platform);

        self::assertInstanceOf(MbStringObject::class, $result);
    }

    public function test_that_convert_to_php_value_returns_same_instance(): void
    {
        $obj = MbStringObject::fromString('énorme texte');
        $result = $this->type->convertToPHPValue($obj, $this->platform);

        self::assertSame($obj, $result);
    }

    public function test_that_convert_to_php_value_creates_mb_string_object_from_string(): void
    {
        $result = $this->type->convertToPHPValue('日本語テキスト', $this->platform);

        self::assertInstanceOf(MbStringObject::class, $result);
        self::assertSame('日本語テキスト', $result->toString());
    }

    public function test_that_convert_to_php_value_throws_for_non_string_value(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue(['not', 'a', 'string'], $this->platform);
    }
}
