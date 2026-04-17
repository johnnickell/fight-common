<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Messaging\Command;

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
 * Class CommandMessage
 */
final class CommandMessage extends BaseMessage
{
    /**
     * Constructs CommandMessage
     */
    public function __construct(MessageId $id, DateTimeImmutable $timestamp, Command $payload, Meta $data)
    {
        parent::__construct(
            $id,
            MessageType::COMMAND,
            $timestamp,
            $payload,
            $data
        );
    }

    /**
     * Creates instance for a command
     */
    public static function create(Command $command): static
    {
        $timestamp = new DateTimeImmutable();
        $id = MessageId::generate();
        $data = Meta::create();

        return new static($id, $timestamp, $command, $data);
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

        if ($data['type'] !== MessageType::COMMAND->value) {
            $message = sprintf('Invalid message type: %s', $data['type']);
            throw new DomainException($message);
        }

        $id = MessageId::fromString($data['id']);
        $timestamp = DateTimeImmutable::createFromFormat('U', $data['timestamp']);
        $data = Meta::create($data['meta']);
        $payloadType = Type::create($data['payload_type']);
        /** @var Command|string $payloadClass */
        $payloadClass = $payloadType->toClassName();

        assert(Validate::implementsInterface($payloadClass, Command::class));

        $payload = $payloadClass::fromArray($data['payload']);

        return new static($id, $timestamp, $payload, $data);
    }

    /**
     * @inheritDoc
     */
    public function withMeta(Meta $data): static
    {
        /** @var Command $command */
        $command = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $command,
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

        /** @var Command $command */
        $command = $this->payload;

        return new static(
            $this->id,
            $this->timestamp,
            $command,
            $meta
        );
    }
}
