<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class DependencyLaneEvidenceAuthority
 *
 * Validates the closed receipt supplied by the optional-dependency lanes.
 */
final class DependencyLaneEvidenceAuthority
{
    /** @var list<string> */
    public const array LANES = [
        'locked', 'lowest', 'latest', 'production', 'symfony', 'laravel', 'yii', 'codeigniter', 'slim'
    ];

    /**
     * Validates one complete dependency-lane receipt
     *
     * @param array<string, mixed> $receipt
     */
    public function isValid(array $receipt): bool
    {
        if (
            ($receipt['schema_version'] ?? null) !== 'fight-common.dependency-lanes/v1'
            || ($receipt['status'] ?? null) !== 'valid'
        ) {
            return false;
        }

        $lanes = $receipt['lanes'] ?? null;
        if (!is_array($lanes) || array_keys($lanes) !== self::LANES) {
            return false;
        }

        return array_all(
            $lanes,
            fn($lane, $name): bool => !(
                !is_array($lane)
                || ($lane['status'] ?? null) !== 'passed'
                || ($lane['name'] ?? null) !== $name
                || !is_string($lane['lock_sha256'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/', $lane['lock_sha256'])
                || !is_array($lane['resolved'] ?? null)
                || !is_array($lane['probes'] ?? null)
                || ($lane['next_action'] ?? null) !== ['action' => 'review_dependency_lane_evidence']
            )
        );
    }

    /**
     * Checks whether an action resumes exactly one known dependency lane
     *
     * @param array<string, mixed> $action Machine next action.
     */
    public function isRetryAction(array $action): bool
    {
        if (array_keys($action) !== ['action'] || !is_string($action['action'] ?? null)) {
            return false;
        }

        return in_array(
            $action['action'],
            array_map(
                static fn (string $lane): string => 'restore_'.$lane.'_dependency_lane_and_retry',
                self::LANES
            ),
            true
        );
    }
}
