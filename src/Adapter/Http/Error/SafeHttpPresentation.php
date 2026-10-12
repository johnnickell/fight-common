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
     * @var array<array-key, mixed>|null
     */
    public ?array $data;

    /**
     * Constructs SafeHttpPresentation
     *
     * @param string|null                  $message  Deliberately public message.
     * @param array<array-key, mixed>|null $data     Deliberately public data; no objects or resources.
     * @param SafeHttpMetadata|null        $metadata Explicit protocol metadata.
     */
    public function __construct(
        public ?string $message = null,
        ?array $data = null,
        public ?SafeHttpMetadata $metadata = null
    ) {
        if ($message !== null && preg_match('//u', $message) !== 1) {
            throw new InvalidArgumentException('Invalid public message.');
        }

        if ($data === null) {
            $this->data = null;

            return;
        }

        // Rebuild every level so PHP array references cannot mutate validated public data later.
        $snapshot = $this->snapshotData($data);
        try {
            json_encode($snapshot, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException('Invalid public data.', previous: $jsonException);
        }

        $this->data = $snapshot;
    }

    /**
     * Returns detached plain JSON without invoking object callbacks
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function snapshotData(array $data, int $depth = 0): array
    {
        if ($depth >= 500) {
            throw new InvalidArgumentException('Public data exceeds the safe nesting depth.');
        }

        $snapshot = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $snapshot[$key] = $this->snapshotData($value, $depth + 1);
            } elseif ($value === null || is_scalar($value)) {
                $snapshot[$key] = $value;
            } else {
                throw new InvalidArgumentException('Public data must contain only plain JSON values.');
            }
        }

        return $snapshot;
    }
}
