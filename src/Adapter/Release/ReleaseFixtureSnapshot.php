<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Release;

/**
 * Class ReleaseFixtureSnapshot
 *
 * Holds one immutable bootstrap parse so capability preflight and orchestration see the same candidate.
 */
final readonly class ReleaseFixtureSnapshot
{
    /**
     * Constructs ReleaseFixtureSnapshot
     *
     * @param 'encoding_invalid'|'invalid'|'unreadable'|'valid' $status    Bootstrap parse status.
     * @param array<string, mixed>|null                         $candidate Parsed candidate snapshot.
     */
    private function __construct(public string $status, public ?array $candidate = null)
    {
    }

    /**
     * Creates a valid fixture snapshot
     *
     * @param array<string, mixed> $candidate Parsed candidate snapshot.
     */
    public static function valid(array $candidate): self
    {
        return new self('valid', $candidate);
    }

    /**
     * Creates an unreadable fixture snapshot
     */
    public static function unreadable(): self
    {
        return new self('unreadable');
    }

    /**
     * Creates an encoding-invalid fixture snapshot
     */
    public static function encodingInvalid(): self
    {
        return new self('encoding_invalid');
    }

    /**
     * Creates a malformed fixture snapshot
     */
    public static function invalid(): self
    {
        return new self('invalid');
    }
}
