<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Value\ObservationReference;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class ObservationIdentity
 *
 * Producer namespace plus existing full UUID; no replacement UUID primitive
 */
final readonly class ObservationIdentity extends ValueObject
{
    /**
     * Constructs ObservationIdentity
     */
    private function __construct(private ObservationReference $producer, private ObservationId $id)
    {
    }

    /**
     * Creates the exact producer-scoped identity of an observation
     */
    public static function fromParts(string $producerId, ObservationId $id): self
    {
        return new self(ObservationReference::fromString($producerId), $id);
    }

    /**
     * Creates an identity from stable tuple text with the complete canonical UUID
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (
            !is_array($data) || !array_is_list($data) || count($data) !== 2 || !is_string($data[1])
            || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $data[1]) !== 1
        ) {
            throw new DomainException('Invalid observation identity.');
        }

        return self::fromParts(
            ObservationReference::fromString($data[0])->toString(),
            ObservationId::fromString($data[1])
        );
    }

    /**
     * Returns the exact producer namespace without admission authority
     */
    public function producerId(): string
    {
        return $this->producer->toString();
    }

    /**
     * Returns the existing full UUID value
     */
    public function id(): ObservationId
    {
        return $this->id;
    }

    /**
     * Returns stable tuple text without ambiguous delimiters
     */
    public function toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the exact producer and full UUID tuple
     *
     * @return array{string, string}
     */
    public function jsonSerialize(): array
    {
        return [$this->producerId(), $this->id->toString()];
    }
}
