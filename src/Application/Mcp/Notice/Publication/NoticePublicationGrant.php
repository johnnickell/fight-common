<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Publication;

use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeMetadata;

/**
 * Class NoticePublicationGrant
 *
 * Selects only retained fields, never arbitrary replacement content or target reads
 */
final readonly class NoticePublicationGrant
{
    public const int MAX_BYTES = 4096;
    public const int MAX_REFERENCES = 32;
    public const array FIELDS = [
        'topic', 'type', 'occurred_at', 'recorded_at',
        'subject', 'recipients', 'author', 'command', 'event', 'action'
    ];

    /**
     * Constructs NoticePublicationGrant
     *
     * @phpstan-param list<string> $fields
     * @phpstan-param list<string> $metadata
     */
    private function __construct(
        private string $topic,
        private bool $private,
        private array $fields,
        private array $metadata
    ) {
    }

    /**
     * Creates a single-topic grant including mandatory scope and producer-bound notice identity
     *
     * @phpstan-param array<array-key, mixed> $fields
     * @phpstan-param array<array-key, mixed> $metadata
     */
    public static function create(string $topic, bool $private, array $fields = [], array $metadata = []): self
    {
        $pattern = <<<'REGEX'
            /\Ahttps:\/\/[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?
            (?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\/[A-Za-z0-9._~\/-]*\z/Dx
            REGEX;
        if (
            strlen($topic) > 256
            || preg_match($pattern, $topic) !== 1
            || !array_is_list($fields) || count($fields) > count(self::FIELDS)
            || !array_is_list($metadata) || count($metadata) > 16
        ) {
            throw new DomainException('Invalid notice publication grant.');
        }

        foreach ($fields as $field) {
            if (!is_string($field) || !in_array($field, self::FIELDS, true)) {
                throw new DomainException('Invalid notice publication field.');
            }
        }

        foreach ($metadata as $key) {
            if (!is_string($key)) {
                throw new DomainException('Invalid notice publication metadata.');
            }
        }

        if (count(array_unique($fields)) !== count($fields) || count(array_unique($metadata)) !== count($metadata)) {
            throw new DomainException('Duplicate notice publication field.');
        }

        NoticeMetadata::fromArray(array_fill_keys($metadata, null));

        return new self(
            $topic,
            $private,
            array_map(static fn (string $field): string => $field, $fields),
            array_map(static fn (string $key): string => $key, $metadata)
        );
    }

    /**
     * Returns the exact permitted transport topic rather than a read or subscription grant
     */
    public function topic(): string
    {
        return $this->topic;
    }

    /**
     * Returns required transport privacy without permitting a public fallback
     */
    public function isPrivate(): bool
    {
        return $this->private;
    }

    /**
     * Creates a bounded encoded projection preserving disclosed nulls and empty recipients
     */
    public function message(NoticeView $view): string
    {
        $source = $view->toArray();
        $data = [
            'version'  => 1,
            'scope'    => $source['scope'],
            'producer' => $source['producer'],
            'id'       => $source['id']
        ];
        $references = 3;
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $source['relationships'])) {
                $value = $source['relationships'][$field];
                $data['relationships'][$field] = $value;
                $references += is_array($value) ? count($value) : (int) ($value !== null);
            } else {
                $data[$field] = $source[$field];
                $references += (int) in_array($field, ['topic', 'type'], true);
            }
        }

        foreach ($this->metadata as $key) {
            if (!array_key_exists($key, $source['metadata'])) {
                throw new DomainException('Unavailable notice publication field.');
            }

            $data['metadata'][$key] = $source['metadata'][$key];
        }

        $message = json_encode($data, JSON_THROW_ON_ERROR);
        if ($references > self::MAX_REFERENCES || strlen($message) > self::MAX_BYTES) {
            throw new DomainException('Notice publication exceeds bounds.');
        }

        return $message;
    }
}
