<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging;

use DateTimeImmutable;
use Fight\Common\Domain\Serialization\Serializable;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Type\Equatable;
use Fight\Common\Domain\Type\Type;
use JsonSerializable;

/**
 * Interface Message
 */
interface Message extends Arrayable, Comparable, Equatable, JsonSerializable, Serializable
{
    /**
     * Retrieves the ID
     */
    public function id(): MessageId;

    /**
     * Retrieves the type
     */
    public function type(): MessageType;

    /**
     * Retrieves the timestamp
     */
    public function timestamp(): DateTimeImmutable;

    /**
     * Retrieves the payload
     */
    public function payload(): Payload;

    /**
     * Retrieves the payload type
     */
    public function payloadType(): Type;

    /**
     * Retrieves the meta data
     */
    public function meta(): Meta;

    /**
     * Creates instance with the given meta
     */
    public function withMeta(Meta $data): static;

    /**
     * Creates instance after merging meta
     */
    public function mergeMeta(Meta $data): static;

    /**
     * Retrieves a string representation
     */
    public function toString(): string;

    /**
     * Handles casting to a string
     */
    public function __toString(): string;

    /**
     * Retrieves an array representation
     */
    public function toArray(): array;

    /**
     * Retrieves a value for JSON encoding
     */
    public function jsonSerialize(): array;
}
