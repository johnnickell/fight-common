<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging\Event;

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
 * Class EventMessage
 */
final class EventMessage extends BaseMessage
{
    /**
     * Constructs EventMessage
     */
    public function __construct(MessageId $id, DateTimeImmutable $timestamp, Event $payload, Meta $data)
    {
        parent::__construct(
            $id,
            MessageType::EVENT,
            $timestamp,
            $payload,
            $data
        );
    }

    /**
     * Creates instance for an event
     */
    public static function create(Event $event): static
    {
        $timestamp = new DateTimeImmutable();
        $id = MessageId::generate();
        $data = Meta::create();

        return new static($id, $timestamp, $event, $data);
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

        if ($data['type'] !== MessageType::EVENT->value) {
            $message = sprintf('Invalid message type: %s', $data['type']);
            throw new DomainException($message);
        }

        $id = MessageId::fromString($data['id']);
        $timestamp = DateTimeImmutable::createFromFormat('U', $data['timestamp']);
        $meta = Meta::create($data['meta']);
        $payloadType = Type::create($data['payload_type']);
        /** @var Event|string $payloadClass */
        $payloadClass = $payloadType->toClassName();

        assert(Validate::implementsInterface($payloadClass, Event::class));

        $payload = $payloadClass::fromArray($data['payload']);

        return new static($id, $timestamp, $payload, $meta);
    }

    /**
     * @inheritDoc
     */
    public function withMeta(Meta $data): static
    {
        /** @var Event $event */
        $event = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $event,
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

        /** @var Event $event */
        $event = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $event,
            $meta
        );
    }
}
