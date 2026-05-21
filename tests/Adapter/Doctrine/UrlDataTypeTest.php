<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\UrlDataType;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UrlDataType::class)]
class UrlDataTypeTest extends UnitTestCase
{
    private UrlDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new UrlDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'url'];
        $this->platform->shouldReceive('getStringTypeDeclarationSQL')->with($column)->andReturn('VARCHAR(2048)');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('VARCHAR(2048)', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(UrlDataType::TYPE_NAME, $this->type->getName());
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

    public function test_that_convert_to_database_value_returns_string_for_url(): void
    {
        $url = Url::fromString('https://example.com/resource');
        $result = $this->type->convertToDatabaseValue($url, $this->platform);

        self::assertSame('https://example.com/resource', $result);
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
        $url = Url::fromString('https://example.com');
        $result = $this->type->convertToPHPValue($url, $this->platform);

        self::assertSame($url, $result);
    }

    public function test_that_convert_to_php_value_creates_url_from_valid_string(): void
    {
        $result = $this->type->convertToPHPValue('https://example.com/page', $this->platform);

        self::assertInstanceOf(Url::class, $result);
    }

    public function test_that_convert_to_php_value_throws_for_invalid_url_string(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-a-valid-url-no-scheme', $this->platform);
    }
}
