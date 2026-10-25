<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class Notice
 */
final readonly class Notice
{
    public const int MAX_BYTES = 6144;

    /**
     * Constructs Notice
     */
    private function __construct(
        private string $id,
        private string $producer,
        private string $scope,
        private string $topic,
        private string $type,
        private ?NoticeOccurrence $occurrence,
        private NoticeMetadata $metadata,
        private NoticeRelationships $relationships
    ) {
    }

    /**
     * Creates a complete version-one safe notice without transport or protected bodies
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        $fields = ['id', 'metadata', 'occurred_at', 'producer', 'relationships', 'scope', 'topic', 'type', 'version'];
        if (
            $keys !== $fields || $data['version'] !== 1
            || !is_array($data['metadata']) || !is_array($data['relationships'])
            || ($data['occurred_at'] !== null && !is_string($data['occurred_at']))
        ) {
            throw new DomainException('Invalid notice structure.');
        }

        $notice = new self(
            self::reference($data['id']),
            self::reference($data['producer']),
            self::reference($data['scope']),
            self::reference($data['topic']),
            self::reference($data['type']),
            $data['occurred_at'] === null ? null : NoticeOccurrence::fromString($data['occurred_at']),
            NoticeMetadata::fromArray($data['metadata']),
            NoticeRelationships::fromArray($data['relationships'])
        );
        if (strlen($notice->comparison()) > self::MAX_BYTES) {
            throw new DomainException('Notice exceeds bounds.');
        }

        return $notice;
    }

    /**
     * Validates a bounded exact ASCII reference without assigning permission or meaning
     */
    public static function reference(mixed $value): string
    {
        if (
            !is_string($value) || strlen($value) > 96
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/D', $value) !== 1
        ) {
            throw new DomainException('Invalid notice reference.');
        }

        return $value;
    }

    /**
     * Returns the producer-scoped notice identity
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the submitted producer reference rather than a credential
     */
    public function producer(): string
    {
        return $this->producer;
    }

    /**
     * Returns the submitted scope rather than an access grant
     */
    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * Returns typed bounded metadata
     */
    public function metadata(): NoticeMetadata
    {
        return $this->metadata;
    }

    /**
     * Returns the independent relationships
     */
    public function relationships(): NoticeRelationships
    {
        return $this->relationships;
    }

    /**
     * Returns the supplied occurrence with explicit unknown and source precision
     */
    public function occurrence(): ?NoticeOccurrence
    {
        return $this->occurrence;
    }

    /**
     * Returns the deterministic versioned comparison excluding server receipt data
     */
    public function comparison(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the complete isolated bounded representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'       => 1,
            'id'            => $this->id,
            'producer'      => $this->producer,
            'scope'         => $this->scope,
            'topic'         => $this->topic,
            'type'          => $this->type,
            'occurred_at'   => $this->occurrence?->toString(),
            'metadata'      => $this->metadata->toArray(),
            'relationships' => $this->relationships->toArray()
        ];
    }
}
