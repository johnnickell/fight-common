<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Serialization;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\ClassName;

/**
 * Class JsonSerializer
 *
 * @deprecated since 1.2, will be removed in 2.0.
 * Use {@see \Fight\Common\Application\Serialization\JsonSerializer} instead.
 */
final class JsonSerializer implements Serializer
{
    /**
     * @inheritDoc
     */
    public function deserialize(string $state): Serializable
    {
        $serializationEnvelope = json_decode($state, true);

        $keys = ['@', '$'];
        foreach ($keys as $key) {
            if (!isset($serializationEnvelope[$key])) {
                $message = sprintf('Invalid serialization format: %s', $state);
                throw new DomainException($message);
            }
        }

        $serializedClass = ClassName::full($serializationEnvelope['@']);

        if (!is_subclass_of($serializedClass, Serializable::class)) {
            throw new DomainException('Class must implement Serializable interface');
        }

        return $serializedClass::arrayDeserialize($serializationEnvelope['$']);
    }

    /**
     * @inheritDoc
     */
    public function serialize(Serializable $object): string
    {
        $serializationEnvelope = [
            '@' => ClassName::canonical($object),
            '$' => $object->arraySerialize()
        ];

        return json_encode($serializationEnvelope, JSON_UNESCAPED_SLASHES);
    }
}
