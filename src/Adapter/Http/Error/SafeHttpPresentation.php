<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Error;

use InvalidArgumentException;
use JsonException;

/**
 * Class SafeHttpPresentation
 *
 * Explicitly public message and plain JSON data supplied by trusted consumer composition.
 */
final readonly class SafeHttpPresentation
{
    /**
     * Constructs SafeHttpPresentation
     *
     * @param string|null                  $message  Deliberately public message.
     * @param array<array-key, mixed>|null $data     Deliberately public data; no objects or resources.
     * @param SafeHttpMetadata|null        $metadata Explicit protocol metadata.
     */
    public function __construct(
        public ?string $message = null,
        public ?array $data = null,
        public ?SafeHttpMetadata $metadata = null
    ) {
        if ($message !== null && preg_match('//u', $message) !== 1) {
            throw new InvalidArgumentException('Invalid public message.');
        }

        if ($data !== null) {
            try {
                $this->validateData($data);
                json_encode($data, JSON_THROW_ON_ERROR);
            } catch (JsonException $failure) {
                throw new InvalidArgumentException('Invalid public data.', previous: $failure);
            }
        }
    }

    /**
     * Validates plain JSON without allowing object serialization or callbacks
     *
     * @param array<array-key, mixed> $data
     */
    private function validateData(array $data, int $depth = 0): void
    {
        if ($depth >= 500) {
            throw new InvalidArgumentException('Public data exceeds the safe nesting depth.');
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $this->validateData($value, $depth + 1);
            } elseif ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Public data must contain only plain JSON values.');
            }
        }
    }
}
