<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Interface StructuralCompatibilityAuthority
 */
interface StructuralCompatibilityAuthority
{
    /**
     * Creates closed structural checker evidence from two authenticated inventories
     *
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    public function checker(array $baseline, array $candidate): array;

    /**
     * Returns authenticated checker evidence without allowing it to define manifest policy
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $checker
     *
     * @return array<string, mixed>
     */
    public function compare(array $manifest, array $baseline, array $candidate, array $checker): array;
}
