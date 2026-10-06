<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Utility\VarPrinter;
use Fight\Common\Domain\Value\ValueObject;
use JsonException;
use Override;

/**
 * Class JsonObject
 */
final readonly class JsonObject extends ValueObject
{
    /**
     * Constructs JsonObject
     *
     * @throws DomainException When the data is not JSON encodable
     */
    private function __construct(
        private mixed $data,
        private int $encodingOptions = JSON_UNESCAPED_SLASHES,
        private ?string $snapshot = null
    ) {
        if ($this->snapshot === null && !Validate::isJsonEncodable($this->data)) {
            $message = sprintf('Unable to JSON encode: %s', VarPrinter::toString($this->data));
            throw new DomainException($message);
        }
    }

    /**
     * Creates instance from data
     *
     * @throws DomainException When the data is not JSON encodable
     */
    public static function fromData(mixed $data, int $encodingOptions = JSON_UNESCAPED_SLASHES): static
    {
        return new static($data, $encodingOptions);
    }

    /**
     * Creates an isolated snapshot of JSON-encodable data
     *
     * Consumer serializers run during capture; their throwables propagate unchanged.
     * Presentation options affect representation equality. Native floats use shortest
     * round-trip precision and retain their zero fraction. See docs/values.md.
     *
     * @throws DomainException When options, encoding or reconstruction are unsupported
     */
    public static function fromSnapshot(mixed $data, int $encodingOptions = JSON_UNESCAPED_SLASHES): static
    {
        $snapshot = self::encodeSnapshot($data, $encodingOptions);
        self::decodeSnapshot($snapshot);

        return new static(null, $encodingOptions, $snapshot);
    }

    /**
     * Creates a snapshot from JSON text using native numeric precision
     *
     * Rejects integer literals outside the native range. Whitespace, escape spelling
     * and float lexemes normalize; object/list and integer/float kinds remain distinct.
     *
     * @throws DomainException When text, options or its representation are unsupported
     */
    public static function fromSnapshotString(string $value, int $encodingOptions = JSON_UNESCAPED_SLASHES): static
    {
        $data = self::decodeSnapshot($value);
        self::validateSnapshotNumbers($value);

        return self::fromSnapshot($data, $encodingOptions);
    }

    /**
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        if (!Validate::isJson($value)) {
            $message = sprintf('Invalid JSON string: %s', $value);
            throw new DomainException($message);
        }

        return new static(json_decode($value, true));
    }

    /**
     * Retrieves data representation
     */
    public function toData(): mixed
    {
        if ($this->snapshot !== null) {
            return self::decodeSnapshot($this->snapshot);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        return json_encode($this->data, $this->encodingOptions);
    }

    /**
     * Retrieves a string representation with given encoding options
     */
    public function encode(int $encodingOptions = JSON_UNESCAPED_SLASHES): string
    {
        if ($this->snapshot !== null) {
            return self::encodeSnapshot($this->toData(), $encodingOptions);
        }

        return json_encode($this->data, $encodingOptions);
    }

    /**
     * Retrieves a pretty print representation
     */
    public function prettyPrint(): string
    {
        if ($this->snapshot !== null) {
            return $this->encode($this->encodingOptions | JSON_PRETTY_PRINT);
        }

        return json_encode($this->data, $this->encodingOptions | JSON_PRETTY_PRINT);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toData();
    }

    /**
     * Encodes snapshot data without confusing serializer throwables with codec failures
     */
    private static function encodeSnapshot(mixed $data, int $options): string
    {
        $allowed = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $allowed |= JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;
        $allowed |= JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION;
        if (($options & ~$allowed) !== 0) {
            throw new DomainException('Unsupported JSON snapshot encoding options.');
        }

        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');
        try {
            // Do not use JSON_THROW_ON_ERROR here: a consumer may throw JsonException itself.
            $json = json_encode($data, $options | JSON_PRESERVE_ZERO_FRACTION, 512);
            if ($json === false) {
                $cause = new JsonException(json_last_error_msg(), json_last_error());
                throw new DomainException('Unable to encode JSON snapshot.', previous: $cause);
            }

            return $json;
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    /**
     * Validates every numeric literal in already validated JSON, including overwritten properties
     */
    private static function validateSnapshotNumbers(string $json): void
    {
        $length = strlen($json);
        $offset = 0;
        while ($offset < $length) {
            $offset += strcspn($json, '"-0123456789', $offset);
            if ($offset === $length) {
                break;
            }

            if ($json[$offset] === '"') {
                ++$offset;
                $offset += strcspn($json, "\"\\", $offset);
                while ($json[$offset] === '\\') {
                    $offset += 2;
                    $offset += strcspn($json, "\"\\", $offset);
                }

                ++$offset;
                continue;
            }

            $size = strspn($json, '-+0123456789.eE', $offset);
            $number = substr($json, $offset, $size);
            if (strpbrk($number, '.eE') === false && $number !== '-0' && (string) (int) $number !== $number) {
                throw new DomainException('Unsupported JSON snapshot representation.');
            }

            $offset += $size;
        }
    }

    /**
     * Decodes a fresh object-mode representation within the snapshot codec limit
     */
    private static function decodeSnapshot(string $json): mixed
    {
        try {
            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new DomainException('Unable to decode JSON snapshot.', previous: $jsonException);
        }
    }
}
