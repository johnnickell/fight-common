<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Common\Domain\Value\ValueObject;
use TypeError;

/**
 * Class TaskInputBatch
 */
final readonly class TaskInputBatch extends ValueObject
{
    /**
     * Constructs TaskInputBatch
     */
    private function __construct(private StrictJson $data)
    {
    }

    /**
     * Creates bounded private contracts bound to a neutral caller and absolute expiry
     */
    public static function create(string $caller, int $expiresAt, bool $confirmation, StrictJson $requests): self
    {
        TaskDefinition::reference($caller);
        Task::timestamp($expiresAt);
        if (!$requests->isObject() || count($requests->properties()) < 1 || count($requests->properties()) > 16) {
            throw new DomainException('Invalid Task input contracts.');
        }

        foreach ($requests->properties() as $name => $request) {
            if (
                !is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $name) !== 1
                || !$request instanceof StrictJson || !$request->isObject()
                || count($request->properties()) !== 3
                || !is_string($request->get('name'))
                || preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $request->get('name')) !== 1
                || !$request->get('public') instanceof StrictJson || !$request->get('contract') instanceof StrictJson
            ) {
                throw new DomainException('Invalid Task input contract.');
            }
        }

        $data = StrictJson::fromObject([
            'caller' => $caller, 'expires_at' => $expiresAt, 'confirmation' => $confirmation, 'requests' => $requests
        ], 40);
        if (strlen($data->toString()) > 24576) {
            throw new DomainException('Task input contracts exceed the encoded bound.');
        }

        return new self($data);
    }

    /**
     * Creates a validated protected batch from stored plain data
     */
    public static function fromData(StrictJson $data): self
    {
        if (!$data->isObject() || count($data->properties()) !== 4) {
            throw new DomainException('Invalid Task input batch.');
        }

        return self::create(
            $data->get('caller'),
            $data->get('expires_at'),
            $data->get('confirmation'),
            $data->get('requests')
        );
    }

    /**
     * Creates bound contracts from structured JSON without acquiring input lifecycle identity
     */
    public static function fromString(string $value): static
    {
        try {
            return self::fromData(StrictJson::fromString($value, 40));
        } catch (TypeError $typeError) {
            throw new DomainException('Invalid Task input batch.', 0, $typeError);
        }
    }

    /**
     * Returns immutable private binding and contracts rather than a public form
     */
    public function data(): StrictJson
    {
        return $this->data;
    }

    /**
     * Returns exact bound contracts including source ordering and numeric kinds
     */
    public function toString(): string
    {
        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        try {
            return $this->data->toString();
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    /**
     * Returns structured private contracts rather than an embedded JSON string
     */
    public function jsonSerialize(): mixed
    {
        return $this->data->jsonSerialize();
    }
}
