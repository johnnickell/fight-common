<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\UriDataType;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UriDataType::class)]
class UriDataTypeTest extends UnitTestCase
{
    private UriDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new UriDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'uri'];
        $this->platform->shouldReceive('getStringTypeDeclarationSQL')->with($column)->andReturn('VARCHAR(2048)');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('VARCHAR(2048)', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(UriDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('https://example.com', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_uri(): void
    {
        $uri = Uri::fromString('https://example.com/path');
        $result = $this->type->convertToDatabaseValue($uri, $this->platform);

        self::assertSame('https://example.com/path', $result);
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
        $uri = Uri::fromString('https://example.com');
        $result = $this->type->convertToPHPValue($uri, $this->platform);

        self::assertSame($uri, $result);
    }

    public function test_that_convert_to_php_value_creates_uri_from_valid_string(): void
    {
        $result = $this->type->convertToPHPValue('https://example.com/api', $this->platform);

        self::assertInstanceOf(Uri::class, $result);
    }

    public function test_that_convert_to_php_value_throws_for_invalid_uri_string(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-a-valid-uri-no-scheme', $this->platform);
    }
}
