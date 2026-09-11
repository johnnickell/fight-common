<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Symfony\Serializer;

use Fight\Common\Adapter\Messaging\Serializer\SymfonyMessageSerializer as LegacySymfonyMessageSerializer;
use Fight\Common\Domain\Serialization\Serializer as DomainSerializer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Class SymfonyMessageSerializer
 */
final readonly class SymfonyMessageSerializer implements SerializerInterface
{
    private LegacySymfonyMessageSerializer $serializer;

    /**
     * Constructs SymfonyMessageSerializer
     */
    public function __construct(DomainSerializer $serializer)
    {
        $this->serializer = new LegacySymfonyMessageSerializer($serializer);
    }

    /**
     * Fails decoding when an unserialized class is unavailable
     *
     * @throws MessageDecodingFailedException
     */
    public static function handleUnserializeCallback(string $class): never
    {
        LegacySymfonyMessageSerializer::handleUnserializeCallback($class);
    }

    /**
     * Decodes a transport envelope with the legacy serializer
     *
     * @inheritDoc
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        return $this->serializer->decode($encodedEnvelope);
    }

    /**
     * Encodes a transport envelope with the legacy serializer
     *
     * @inheritDoc
     */
    public function encode(Envelope $envelope): array
    {
        return $this->serializer->encode($envelope);
    }
}
