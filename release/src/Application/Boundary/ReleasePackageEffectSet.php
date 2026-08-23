<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use Fight\Release\Application\CanonicalJson;

/**
 * Class ReleasePackageEffectSet
 *
 * Owns the exact bounded local effect set a packaging operation would perform,
 * its content-addressed identity, and the vocabulary for verifying its approval.
 */
final class ReleasePackageEffectSet
{
    public const string SCHEMA_VERSION = 'fight-common.package-effect-set/v1';

    public string $effectSetId;
    /** @var list<string> */
    public array $includedPaths;
    /** @var list<string> */
    public array $excludedPaths;

    /**
     * Constructs ReleasePackageEffectSet
     *
     * @phpstan-param list<string> $includedPaths
     * @phpstan-param list<string> $excludedPaths
     */
    public function __construct(
        public string $candidateOid,
        public string $version,
        public string $archiveName,
        array $includedPaths,
        array $excludedPaths,
        private readonly CanonicalJson $json = new CanonicalJson()
    ) {
        $sortedIncluded = $includedPaths;
        sort($sortedIncluded, SORT_STRING);
        $sortedExcluded = $excludedPaths;
        sort($sortedExcluded, SORT_STRING);

        $canonical = $this->json->encode([
            'schema_version' => self::SCHEMA_VERSION,
            'candidate_oid'  => $this->candidateOid,
            'version'        => $this->version,
            'archive_name'   => $this->archiveName,
            'included_paths' => $sortedIncluded,
            'excluded_paths' => $sortedExcluded
        ]);

        $this->includedPaths = $sortedIncluded;
        $this->excludedPaths = $sortedExcluded;
        $this->effectSetId = hash('sha256', $canonical);
    }

    /**
     * Returns the effect-set identity and canonical paths as public artifact data
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'effect_set_id'  => $this->effectSetId,
            'candidate_oid'  => $this->candidateOid,
            'version'        => $this->version,
            'archive_name'   => $this->archiveName,
            'included_paths' => $this->includedPaths,
            'excluded_paths' => $this->excludedPaths
        ];
    }

    /**
     * Reports whether another effect set represents the exact same bounded effects
     */
    public function matches(self $other): bool
    {
        return $this->effectSetId === $other->effectSetId;
    }
}
