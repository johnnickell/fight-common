<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

/**
 * Enum ReleaseEffect
 *
 * Owns the closed release-effect vocabulary and its capability relationships.
 */
enum ReleaseEffect: string
{
    case FILESYSTEM_READ = 'filesystem.read';
    case FILESYSTEM_WRITE = 'filesystem.write';
    case FILESYSTEM_INSPECT_DIRECTORY = 'filesystem.inspect_directory';
    case FILESYSTEM_INSPECT_WRITABLE = 'filesystem.inspect_writable';
    case FILESYSTEM_INSPECT_EXISTS = 'filesystem.inspect_exists';
    case FILESYSTEM_INSPECT_RUNS_DIRECTORY = 'filesystem.inspect_runs_directory';
    case ARCHIVE_CREATE = 'archive.create';
    case ARCHIVE_VERIFY = 'archive.verify';
    case GIT_INSPECT_REPOSITORY = 'git.inspect_repository';
    case GIT_RESOLVE_REF = 'git.resolve_ref';
    case HASHING_SHA256 = 'hashing.sha256';
    case CLOCK_NOW = 'clock.now';
    case SIGNING_VERIFY = 'signing.verify';
    case AUTHORIZATION_CHECK = 'authorization.check';
    case GITHUB_RELEASE = 'github.release';
    case PACKAGIST_PUBLISH = 'packagist.publish';

    /**
     * Returns all effect-class values in canonical set order
     *
     * @return list<string>
     */
    public static function canonicalValues(): array
    {
        $values = array_map(static fn (self $effect): string => $effect->value, self::cases());
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * Returns the exact capability that owns this effect
     */
    public function capability(): string
    {
        return match ($this) {
            self::FILESYSTEM_READ,
            self::FILESYSTEM_WRITE,
            self::FILESYSTEM_INSPECT_DIRECTORY,
            self::FILESYSTEM_INSPECT_WRITABLE,
            self::FILESYSTEM_INSPECT_EXISTS,
            self::FILESYSTEM_INSPECT_RUNS_DIRECTORY => 'filesystem',
            self::ARCHIVE_CREATE,
            self::ARCHIVE_VERIFY => 'archive',
            self::GIT_INSPECT_REPOSITORY,
            self::GIT_RESOLVE_REF => 'git',
            self::HASHING_SHA256 => 'hashing',
            self::CLOCK_NOW => 'clock',
            self::SIGNING_VERIFY => 'signing',
            self::AUTHORIZATION_CHECK => 'authorization',
            self::GITHUB_RELEASE => 'github',
            self::PACKAGIST_PUBLISH => 'packagist'
        };
    }

    /**
     * Reports whether a completed invocation may observe an already-satisfied postcondition
     */
    public function allowsAlreadySatisfiedOutcome(): bool
    {
        return match ($this) {
            self::FILESYSTEM_WRITE,
            self::ARCHIVE_CREATE,
            self::GITHUB_RELEASE,
            self::PACKAGIST_PUBLISH => true,
            default => false,
        };
    }

    /**
     * Returns the evidence for a directly configurable already-satisfied postcondition
     */
    public function configuredAlreadySatisfiedEvidence(): ?string
    {
        return match ($this) {
            self::ARCHIVE_CREATE => 'archive_already_exists',
            self::GITHUB_RELEASE => 'github_release_exists',
            self::PACKAGIST_PUBLISH => 'packagist_version_exists',
            default => null,
        };
    }
}
