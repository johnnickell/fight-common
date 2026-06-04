<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\AuditEntryIdDataType;
use Fight\Common\Domain\Observability\AuditEntryId;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuditEntryIdDataType::class)]
class AuditEntryIdDataTypeTest extends UnitTestCase
{
    private AuditEntryIdDataType $type;

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new AuditEntryIdDataType();
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
        self::assertSame(AuditEntryIdDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('not-an-audit-entry-id', $this->platform);
    }

    public function test_that_convert_to_database_value_returns_string_for_audit_entry_id(): void
    {
        $id = AuditEntryId::generate();
        $result = $this->type->convertToDatabaseValue($id, $this->platform);

        self::assertSame($id->toString(), $result);
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
        $id = AuditEntryId::generate();
        $result = $this->type->convertToPHPValue($id, $this->platform);

        self::assertSame($id, $result);
    }

    public function test_that_convert_to_php_value_creates_from_valid_string(): void
    {
        $id = AuditEntryId::generate();
        $result = $this->type->convertToPHPValue($id->toString(), $this->platform);

        self::assertInstanceOf(AuditEntryId::class, $result);
        self::assertTrue($id->equals($result));
    }

    public function test_that_convert_to_php_value_throws_for_invalid_string(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-a-valid-id', $this->platform);
    }
}
