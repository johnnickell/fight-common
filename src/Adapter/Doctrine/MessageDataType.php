<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Domain\Messaging\Message;
use Fight\Common\Domain\Serialization\JsonSerializer;
use Throwable;

/**
 * Class MessageDataType
 */
final class MessageDataType extends Type
{
    public const TYPE_NAME = 'common_message';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if (!isset($column['length'])) {
            $column['length'] = 4294967295;
        }

        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * Converts a value from its PHP representation to its database representation
     *
     * @throws ConversionException When the conversion fails
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!($value instanceof Message)) {
            throw InvalidType::new($value, 'string', [Message::class]);
        }

        $serializer = new JsonSerializer();

        return $serializer->serialize($value);
    }

    /**
     * Converts a value from its database representation to its PHP representation
     *
     * @throws ConversionException When the conversion fails
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Message
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Message) {
            return $value;
        }

        try {
            $serializer = new JsonSerializer();

            /** @var Message $message */
            $message = $serializer->deserialize($value);

            return $message;
        } catch (Throwable $e) {
            throw ValueNotConvertible::new($value, Message::class, $e->getMessage(), $e);
        }
    }

    /**
     * Gets the name of this type
     */
    public function getName(): string
    {
        return static::TYPE_NAME;
    }
}
