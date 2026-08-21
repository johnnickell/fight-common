<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

/**
 * Class CanonicalJson
 *
 * Produces the stable JSON representation used for release identities.
 */
final class CanonicalJson
{
    /**
     * Encodes an array with recursively sorted object keys
     *
     * @param array<string, mixed> $value Value to encode.
     */
    public function encode(array $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Sorts nested JSON values recursively
     */
    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->canonicalize(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $entry) {
            $value[$key] = $this->canonicalize($entry);
        }

        return $value;
    }
}
