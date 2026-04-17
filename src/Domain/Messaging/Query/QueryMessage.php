<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging\Query;

use DateTimeImmutable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\BaseMessage;
use Fight\Common\Domain\Messaging\MessageId;
use Fight\Common\Domain\Messaging\MessageType;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Utility\VarPrinter;

/**
 * Class QueryMessage
 */
final class QueryMessage extends BaseMessage
{
    /**
     * Constructs QueryMessage
     */
    public function __construct(MessageId $id, DateTimeImmutable $timestamp, Query $payload, Meta $data)
    {
        parent::__construct(
            $id,
            MessageType::QUERY,
            $timestamp,
            $payload,
            $data
        );
    }

    /**
     * Creates instance for a query
     */
    public static function create(Query $query): static
    {
        $timestamp = new DateTimeImmutable();
        $id = MessageId::generate();
        $data = Meta::create();

        return new static($id, $timestamp, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public static function arrayDeserialize(array $data): static
    {
        $keys = [
            'id',
            'type',
            'timestamp',
            'meta',
            'payload_type',
            'payload'
        ];

        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                $message = sprintf('Invalid serialization data: %s', VarPrinter::toString($data));
                throw new DomainException($message);
            }
        }

        if ($data['type'] !== MessageType::QUERY->value) {
            $message = sprintf('Invalid message type: %s', $data['type']);
            throw new DomainException($message);
        }

        $id = MessageId::fromString($data['id']);
        $timestamp = DateTimeImmutable::createFromFormat('U', $data['timestamp']);
        $data = Meta::create($data['meta']);
        $payloadType = Type::create($data['payload_type']);
        /** @var Query|string $payloadClass */
        $payloadClass = $payloadType->toClassName();

        assert(Validate::implementsInterface($payloadClass, Query::class));

        $payload = $payloadClass::fromArray($data['payload']);

        return new static($id, $timestamp, $payload, $data);
    }

    /**
     * @inheritDoc
     */
    public function withMeta(Meta $data): static
    {
        /** @var Query $query */
        $query = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $query,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function mergeMeta(Meta $data): static
    {
        $meta = clone $this->meta;
        $meta->merge($data);

        /** @var Query $query */
        $query = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $query,
            $meta
        );
    }
}
