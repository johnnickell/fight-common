<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Observability;

use DateTimeImmutable;
use DateTimeInterface;
use Fight\Common\Domain\Messaging\Meta;
use JsonSerializable;

/**
 * Class AuditEntry
 */
readonly class AuditEntry implements JsonSerializable
{
    /**
     * Constructs AuditEntry
     */
    public function __construct(
        private AuditEntryId $id,
        private string $actor,
        private string $action,
        private DateTimeImmutable $timestamp,
        private Meta $context
    ) {
    }

    /**
     * Records a new audit entry at the current time
     *
     * @param string $actor
     * @param string $action
     * @param array<string, mixed> $context
     */
    public static function record(string $actor, string $action, array $context = []): static
    {
        /** @phpstan-ignore new.static */
        return new static(
            AuditEntryId::generate(),
            $actor,
            $action,
            new DateTimeImmutable(),
            Meta::create($context)
        );
    }

    /**
     * Retrieves the entry identifier
     */
    public function id(): AuditEntryId
    {
        return $this->id;
    }

    /**
     * Retrieves the actor (who performed the action)
     */
    public function actor(): string
    {
        return $this->actor;
    }

    /**
     * Retrieves the action name
     */
    public function action(): string
    {
        return $this->action;
    }

    /**
     * Retrieves the timestamp
     */
    public function timestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Retrieves the context metadata
     */
    public function context(): Meta
    {
        return $this->context;
    }

    /**
     * Retrieves an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'        => $this->id->toString(),
            'actor'     => $this->actor,
            'action'    => $this->action,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'context'   => $this->context->toArray()
        ];
    }

    /**
     * Returns data for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
