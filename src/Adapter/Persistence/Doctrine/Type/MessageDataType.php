<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Common\Domain\Messaging\Message;
use Override;
use Throwable;

/**
 * Class MessageDataType
 */
class MessageDataType extends Type
{
    public const string TYPE_NAME = 'common_message';

    /**
     * Gets the SQL declaration snippet for a field of this type
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] ??= 4294967295;

        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[Override]
    /**
     * Converts a value from its PHP representation to its database representation
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

    #[Override]
    /**
     * Converts a value from its database representation to its PHP representation
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
        } catch (Throwable $throwable) {
            throw ValueNotConvertible::new($value, Message::class, $throwable->getMessage(), $throwable);
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
