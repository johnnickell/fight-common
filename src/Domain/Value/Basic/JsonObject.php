<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;
use Fight\Common\Domain\Utility\VarPrinter;

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
    private function __construct(private mixed $data, private int $encodingOptions = JSON_UNESCAPED_SLASHES)
    {
        if (!Validate::isJsonEncodable($this->data)) {
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
     * @inheritDoc
     */
    public static function fromString(string $value): static
    {
        if (!Validate::isJson($value)) {
            $message = sprintf('Invalid JSON string: %s', $value);
            throw new DomainException($message);
        }

        return new static(json_decode($value, $assoc = true));
    }

    /**
     * Retrieves data representation
     */
    public function toData(): mixed
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return json_encode($this->data, $this->encodingOptions);
    }

    /**
     * Retrieves a string representation with given encoding options
     */
    public function encode(int $encodingOptions = JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->data, $encodingOptions);
    }

    /**
     * Retrieves a pretty print representation
     */
    public function prettyPrint(): string
    {
        return json_encode($this->data, $this->encodingOptions | JSON_PRETTY_PRINT);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): mixed
    {
        return $this->data;
    }
}
