<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging;

use DateTimeImmutable;
use Fight\Common\Domain\Type\Type;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class BaseMessage
 */
abstract class BaseMessage implements Message
{
    protected Type $payloadType;

    /**
     * Constructs BaseMessage
     */
    protected function __construct(
        protected MessageId $id,
        protected MessageType $type,
        protected DateTimeImmutable $timestamp,
        protected Payload $payload,
        protected Meta $meta
    ) {
        $this->payloadType = Type::create($this->payload);
    }

    /**
     * @inheritDoc
     */
    public function id(): MessageId
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function type(): MessageType
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * @inheritDoc
     */
    public function payload(): Payload
    {
        return $this->payload;
    }

    /**
     * @inheritDoc
     */
    public function payloadType(): Type
    {
        return $this->payloadType;
    }

    /**
     * @inheritDoc
     */
    public function meta(): Meta
    {
        return $this->meta;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id->toString(),
            'type'         => $this->type->value,
            'timestamp'    => $this->timestamp->toString(),
            'payload_type' => $this->payloadType->toString(),
            'payload'      => $this->payload->toArray(),
            'meta'         => $this->meta->toArray()
        ];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function arraySerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function compareTo(mixed $other): int
    {
        if ($this === $other) {
            return 0;
        }

        assert(Validate::areSameType($this, $other));

        return $this->id->compareTo($other->id);
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $object): bool
    {
        if ($this === $object) {
            return true;
        }

        if (!Validate::areSameType($this, $object)) {
            return false;
        }

        return $this->id->equals($object->id);
    }

    /**
     * @inheritDoc
     */
    public function hashValue(): string
    {
        return sprintf(
            '%s:%s',
            ClassName::short(static::class),
            $this->id->hashValue()
        );
    }
}
