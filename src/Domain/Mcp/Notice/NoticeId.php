<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\ValueObject;

/**
 * Class NoticeId
 *
 * Exact scope/producer/local-ID tuple, not a position or retention incarnation
 */
final readonly class NoticeId extends ValueObject
{
    /**
     * Constructs NoticeId
     */
    private function __construct(private string $scope, private string $producer, private string $id)
    {
    }

    /**
     * Creates an exact scoped Notice identity using Notice's independent grammar
     */
    public static function fromStrings(string $scope, string $producer, string $id): self
    {
        return new self(Notice::reference($scope), Notice::reference($producer), Notice::reference($id));
    }

    /**
     * Creates an identity from stable tuple text without delimiter ambiguity
     */
    public static function fromString(string $value): self
    {
        $data = json_decode($value, true);
        if (!is_array($data) || !array_is_list($data) || count($data) !== 3) {
            throw new DomainException('Invalid notice identity.');
        }

        return new self(Notice::reference($data[0]), Notice::reference($data[1]), Notice::reference($data[2]));
    }

    /**
     * Returns the exact scope reference without granting access
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns the exact producer reference without authenticating it
     */
    public function producer(): string
    {
        return $this->producer;
    }

    /**
     * Returns the exact local notice reference
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns stable tuple text without normalizing component bytes
     */
    public function toString(): string
    {
        return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the exact scoped identity tuple
     *
     * @return array{string, string, string}
     */
    public function jsonSerialize(): array
    {
        return [$this->scope, $this->producer, $this->id];
    }
}
