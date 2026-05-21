<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\EmailAddressDataType;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EmailAddressDataType::class)]
class EmailAddressDataTypeTest extends UnitTestCase
{
    private EmailAddressDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new EmailAddressDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_delegates_to_platform(): void
    {
        $column = ['name' => 'email'];
        $this->platform->shouldReceive('getStringTypeDeclarationSQL')->with($column)->andReturn('VARCHAR(255)');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('VARCHAR(255)', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(EmailAddressDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('user@example.com', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_email_address(): void
    {
        $obj = EmailAddress::fromString('user@example.com');
        $result = $this->type->convertToDatabaseValue($obj, $this->platform);

        self::assertSame('user@example.com', $result);
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
        $obj = EmailAddress::fromString('admin@example.com');
        $result = $this->type->convertToPHPValue($obj, $this->platform);

        self::assertSame($obj, $result);
    }

    public function test_that_convert_to_php_value_creates_email_address_from_string(): void
    {
        $result = $this->type->convertToPHPValue('test@example.com', $this->platform);

        self::assertInstanceOf(EmailAddress::class, $result);
        self::assertSame('test@example.com', $result->toString());
    }

    public function test_that_convert_to_php_value_throws_for_non_string_value(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue(['not', 'a', 'string'], $this->platform);
    }
}
