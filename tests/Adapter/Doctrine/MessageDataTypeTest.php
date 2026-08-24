<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Fight\Common\Adapter\Doctrine\MessageDataType;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Message;
use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessageDataType::class)]
class MessageDataTypeTest extends UnitTestCase
{
    private MessageDataType $type;

    /** @var MockInterface|AbstractPlatform */
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = new MessageDataType();
        $this->platform = $this->mock(AbstractPlatform::class);
    }

    public function test_that_get_sql_declaration_sets_default_length_when_not_provided(): void
    {
        $column = ['name' => 'payload'];
        $this->platform->shouldReceive('getJsonTypeDeclarationSQL')
            ->withArgs(fn(array $c): bool => $c['length'] === 4294967295)
            ->andReturn('LONGBLOB');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('LONGBLOB', $result);
    }

    public function test_that_get_sql_declaration_uses_existing_length_when_provided(): void
    {
        $column = ['name' => 'payload', 'length' => 65535];
        $this->platform->shouldReceive('getJsonTypeDeclarationSQL')
            ->withArgs(fn(array $c): bool => $c['length'] === 65535)
            ->andReturn('MEDIUMBLOB');

        $result = $this->type->getSQLDeclaration($column, $this->platform);

        self::assertSame('MEDIUMBLOB', $result);
    }

    public function test_that_get_name_returns_type_name_constant(): void
    {
        self::assertSame(MessageDataType::TYPE_NAME, $this->type->getName());
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
        $this->type->convertToDatabaseValue('not-a-message', $this->platform);
    }

    public function test_that_convert_to_database_value_serializes_message_to_json(): void
    {
        $message = CommandMessage::create(new SampleCommand('test'));
        $result = $this->type->convertToDatabaseValue($message, $this->platform);

        self::assertIsString($result);
        self::assertStringContainsString('SampleCommand', $result);
    }

    public function test_that_convert_to_php_value_returns_null_for_null(): void
    {
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function test_that_convert_to_php_value_returns_null_for_empty_string(): void
    {
        self::assertNull($this->type->convertToPHPValue('', $this->platform));
    }

    public function test_that_convert_to_php_value_returns_same_message_instance(): void
    {
        $message = CommandMessage::create(new SampleCommand('test'));
        $result = $this->type->convertToPHPValue($message, $this->platform);

        self::assertSame($message, $result);
    }

    public function test_that_convert_to_php_value_deserializes_valid_serialized_message(): void
    {
        $message = CommandMessage::create(new SampleCommand('roundtrip'));
        $serializer = new JsonSerializer();
        $serialized = $serializer->serialize($message);

        $result = $this->type->convertToPHPValue($serialized, $this->platform);

        self::assertInstanceOf(Message::class, $result);
    }

    public function test_that_convert_to_php_value_throws_for_invalid_json(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-valid-json-at-all', $this->platform);
    }
}
