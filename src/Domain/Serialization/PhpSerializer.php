<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Serialization;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\ClassName;

/**
 * Class PhpSerializer
 */
final class PhpSerializer implements Serializer
{
    /**
     * @inheritDoc
     */
    public function deserialize(string $state): Serializable
    {
        $data = @unserialize($state);

        $keys = ['@', '$'];
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                $message = sprintf('Invalid serialization format: %s', $state);
                throw new DomainException($message);
            }
        }

        $class = ClassName::full($data['@']);

        if (!is_subclass_of($class, Serializable::class)) {
            throw new DomainException('Class must implement Serializable interface');
        }

        return $class::arrayDeserialize($data['$']);
    }

    /**
     * @inheritDoc
     */
    public function serialize(Serializable $object): string
    {
        $data = [
            '@' => ClassName::canonical($object),
            '$' => $object->arraySerialize(),
        ];

        return serialize($data);
    }
}
