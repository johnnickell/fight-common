<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NoticeMetadata
 */
final readonly class NoticeMetadata
{
    /**
     * Constructs NoticeMetadata
     *
     * @param array<string, string|integer|boolean|null> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Creates bounded scalar labels rather than a body or nested payload channel
     *
     * Consumers must select safe values; structural validation cannot detect secrets
     *
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if (count($values) > 16) {
            throw new DomainException('Notice metadata exceeds bounds.');
        }

        $snapshot = [];
        foreach ($values as $key => $value) {
            if (
                !is_string($key) || strlen($key) > 64
                || preg_match('/\A[a-z][a-z0-9.-]*\/[a-z][a-z0-9._-]*\z/D', $key) !== 1
                || (!is_string($value) && !is_int($value) && !is_bool($value) && $value !== null)
                || (is_string($value) && (strlen($value) > 128 || preg_match('//u', $value) !== 1
                    || preg_match('/[\x00-\x1f\x7f]/', $value) === 1))
            ) {
                throw new DomainException('Invalid notice metadata.');
            }

            $snapshot[$key] = $value;
        }

        ksort($snapshot, SORT_STRING);
        if (strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)) > 2048) {
            throw new DomainException('Notice metadata exceeds bounds.');
        }

        return new self($snapshot);
    }

    /**
     * Returns an isolated scalar snapshot
     *
     * @return array<string, string|int|bool|null>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
