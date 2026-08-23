<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Enum ReleaseCommand
 *
 * Owns the stable command metadata shared by release result producers and validators.
 */
enum ReleaseCommand: string
{
    case INSPECT = 'inspect';
    case PLAN = 'plan';
    case PREPARE = 'prepare';
    case PACKAGE = 'package';
    case COMPATIBILITY = 'compatibility';

    public const string UNSUPPORTED_CAPABILITY = 'unsupported_command';

    /**
     * Resolves a requested command for the infrastructure failure envelope
     *
     * @return array{0: string, 1: string}
     */
    public static function runtimeMetadata(string $requestedCommand): array
    {
        $command = self::tryFrom($requestedCommand);

        if ($command === null) {
            return ['unknown', self::UNSUPPORTED_CAPABILITY];
        }

        return [$command->value, $command->capability()];
    }

    /**
     * Resolves a capability without normalizing the caller's command name
     */
    public static function capabilityFor(string $command): string
    {
        return self::tryFrom($command)?->capability() ?? self::UNSUPPORTED_CAPABILITY;
    }

    /**
     * Reports whether a command belongs in an infrastructure failure envelope
     */
    public static function isRuntimeCommand(string $command): bool
    {
        return $command === 'unknown' || self::tryFrom($command) !== null;
    }

    /**
     * Resolves the capability governed by this command
     */
    public function capability(): string
    {
        return match ($this) {
            self::INSPECT => 'release_inspection',
            self::PLAN => 'release_planning',
            self::PREPARE => 'release_preparation',
            self::PACKAGE => 'release_packaging',
            self::COMPATIBILITY => 'compatibility_assessment'
        };
    }

    /**
     * Returns command-specific fields admitted by the stable result envelope
     *
     * @return list<string>
     */
    public function optionalResultFields(): array
    {
        return match ($this) {
            self::INSPECT => ['resolved_inputs', 'recommendation'],
            self::PLAN => ['plan_id', 'artifact'],
            self::PREPARE => ['plan_id', 'run_id', 'run_state', 'artifacts'],
            self::PACKAGE => ['plan_id', 'run_id', 'candidate_oid', 'archive_digest', 'effect_set'],
            self::COMPATIBILITY => ['evidence']
        };
    }
}
