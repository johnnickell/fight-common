<?php

declare(strict_types=1);

namespace Fight\Common\Application\Serialization;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Serialization\Serializable;
use Fight\Common\Domain\Serialization\Serializer;
use Fight\Common\Domain\Utility\ClassName;

/**
 * Class PhpSerializer
 */
class PhpSerializer implements Serializer
{
    /**
     * @inheritDoc
     */
    public function deserialize(string $state): Serializable
    {
        $serializationEnvelope = @unserialize($state);

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

        return serialize($serializationEnvelope);
    }
}
