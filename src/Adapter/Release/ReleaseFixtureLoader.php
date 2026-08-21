<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release;

use Fight\Common\Application\Release\Utf8Validator;
use stdClass;
use Throwable;

/**
 * Class ReleaseFixtureLoader
 *
 * Reads one bootstrap fixture snapshot without entering the release capability ledger.
 */
final readonly class ReleaseFixtureLoader
{
    /**
     * Constructs ReleaseFixtureLoader
     */
    public function __construct(
        private Utf8Validator $utf8 = new Utf8Validator(),
        private ReleaseJsonObjectMemberValidator $members = new ReleaseJsonObjectMemberValidator()
    ) {
    }

    /**
     * Reads and decodes one fixture exactly once
     */
    public function load(string $path): ReleaseFixtureSnapshot
    {
        try {
            $fixture = @file_get_contents(
                $path,
                false,
                null,
                0,
                ReleaseJsonObjectMemberValidator::MAXIMUM_BYTES + 1
            );

            if (!is_string($fixture)) {
                return ReleaseFixtureSnapshot::unreadable();
            }

            if (!$this->utf8->isValid($fixture)) {
                return ReleaseFixtureSnapshot::encodingInvalid();
            }

            if (!$this->members->isValid($fixture)) {
                return ReleaseFixtureSnapshot::invalid();
            }

            $candidate = json_decode($fixture, false, flags: JSON_THROW_ON_ERROR);

            if (!$candidate instanceof stdClass) {
                return ReleaseFixtureSnapshot::invalid();
            }

            return ReleaseFixtureSnapshot::valid($this->normalizeObject($candidate));
        } catch (Throwable) {
            return ReleaseFixtureSnapshot::invalid();
        }
    }

    /**
     * Converts the one decoded object graph to the Application's associative candidate representation
     *
     * @return array<string, mixed>
     */
    private function normalizeObject(stdClass $candidate): array
    {
        $normalized = [];

        foreach (get_object_vars($candidate) as $field => $value) {
            $normalized[$field] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    /**
     * Converts nested JSON objects without reparsing the immutable fixture snapshot
     */
    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return $this->normalizeObject($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        return array_map($this->normalizeValue(...), $value);
    }
}
