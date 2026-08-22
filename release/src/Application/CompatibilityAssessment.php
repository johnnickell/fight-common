<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class CompatibilityAssessment
 *
 * Validates and aggregates the complete composed compatibility evidence set.
 */
final readonly class CompatibilityAssessment
{
    /** @var list<string> */
    public const array CATEGORIES = [
        'structural-api',
        'compatibility-manifest',
        'composer-constraints',
        'package-surface',
        'archive-contents',
        'behavioral-fixtures',
        'serialization-fixtures',
        'persistence-fixtures',
        'adapter-fixtures',
        'dependency-lowest',
        'dependency-locked',
        'dependency-latest',
        'static-analysis',
        'deprecation-discipline'
    ];

    /** @var list<string> */
    private const array ENTRY_FIELDS = ['category', 'finding_id', 'evidence_id', 'classification'];
    /** @var array<string, int> */
    private const array RANKS = ['patch' => 0, 'minor' => 1, 'major' => 2];

    /**
     * Constructs CompatibilityAssessment
     */
    public function __construct(private ReleaseAuthorityValidator $authority = new ReleaseAuthorityValidator())
    {
    }

    /**
     * Validates, canonicalizes, and aggregates independently classified category evidence
     *
     * @return array{status: string, categories: list<array<string, string>>, minimum_increment: string|null}
     */
    public function assess(mixed $candidate): array
    {
        if (!is_array($candidate) || !array_is_list($candidate) || count($candidate) !== count(self::CATEGORIES)) {
            return $this->invalid();
        }

        $categories = [];

        foreach ($candidate as $entry) {
            if (!$this->isEntry($entry)) {
                return $this->invalid();
            }

            /** @var array{category: string, finding_id: string, evidence_id: string, classification: string} $entry */
            if (isset($categories[$entry['category']])) {
                return $this->invalid();
            }

            $categories[$entry['category']] = [
                'category'       => $entry['category'],
                'finding_id'     => $entry['finding_id'],
                'evidence_id'    => $entry['evidence_id'],
                'classification' => $entry['classification']
            ];
        }

        $ordered = [];
        $minimum = 'patch';

        foreach (self::CATEGORIES as $category) {
            $entry = $categories[$category];
            $ordered[] = $entry;

            if ($entry['classification'] === 'indeterminate') {
                return ['status' => 'indeterminate', 'categories' => $ordered, 'minimum_increment' => null];
            }

            if (self::RANKS[$entry['classification']] > self::RANKS[$minimum]) {
                $minimum = $entry['classification'];
            }
        }

        return ['status' => 'valid', 'categories' => $ordered, 'minimum_increment' => $minimum];
    }

    /**
     * Reports whether one record is exact, category-scoped evidence
     */
    private function isEntry(mixed $entry): bool
    {
        if (
            !is_array($entry)
            || count($entry) !== count(self::ENTRY_FIELDS)
            || array_diff(array_keys($entry), self::ENTRY_FIELDS) !== []
            || !is_string($entry['category'])
            || !in_array($entry['category'], self::CATEGORIES, true)
            || !is_string($entry['finding_id'])
            || !str_starts_with($entry['finding_id'], 'release.compatibility.'.$entry['category'].'.')
            || !$this->authority->isAuthorityId($entry['finding_id'])
            || !is_string($entry['evidence_id'])
            || !str_starts_with($entry['evidence_id'], 'evidence.compatibility.'.$entry['category'].'.')
            || !$this->authority->isEvidenceRequirementId($entry['evidence_id'])
            || !is_string($entry['classification'])
        ) {
            return false;
        }

        return isset(self::RANKS[$entry['classification']]) || $entry['classification'] === 'indeterminate';
    }

    /**
     * Returns the closed invalid assessment shape
     *
     * @return array{status: string, categories: list<array<string, string>>, minimum_increment: null}
     */
    private function invalid(): array
    {
        return ['status' => 'invalid', 'categories' => [], 'minimum_increment' => null];
    }
}
