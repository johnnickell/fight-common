<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Release;

use Fight\Common\Application\Release\CompatibilityAssessment;
use Fight\Common\Application\Release\ReleaseAuthorityValidator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers complete compatibility evidence aggregation. */
#[CoversClass(CompatibilityAssessment::class)]
#[CoversClass(ReleaseAuthorityValidator::class)]
class CompatibilityAssessmentTest extends UnitTestCase
{
    /**
     * Covers maximum aggregation independently of input order.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_assess_derives_the_maximum_category_minimum_from_a_complete_unique_set(): void
    {
        $assessment = new CompatibilityAssessment();

        $allPatch = $assessment->assess(array_reverse($this->evidence()));
        self::assertSame('valid', $allPatch['status']);
        self::assertSame('patch', $allPatch['minimum_increment']);
        self::assertSame(CompatibilityAssessment::CATEGORIES, array_column($allPatch['categories'], 'category'));

        $minor = $this->evidence();
        $minor[5]['classification'] = 'minor';
        self::assertSame('minor', $assessment->assess($minor)['minimum_increment']);

        $major = $minor;
        $major[12]['classification'] = 'major';
        self::assertSame('major', $assessment->assess($major)['minimum_increment']);
    }

    /**
     * Covers JSON object member-order equivalence with canonical output.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_assess_accepts_permuted_entry_members_and_emits_canonical_entries(): void
    {
        $assessment = new CompatibilityAssessment();
        $canonical = $assessment->assess($this->evidence());
        $permutedEvidence = array_map(
            static fn (array $entry): array => array_reverse($entry, true),
            array_reverse($this->evidence())
        );
        $permuted = $assessment->assess($permutedEvidence);

        self::assertSame($canonical, $permuted);

        foreach ($permuted['categories'] as $entry) {
            self::assertSame(
                ['category', 'finding_id', 'evidence_id', 'classification'],
                array_keys($entry)
            );
        }
    }

    /**
     * Covers every malformed or incomplete evidence-set stop.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_assess_rejects_missing_duplicate_unknown_and_malformed_category_evidence(): void
    {
        $assessment = new CompatibilityAssessment();
        $valid = $this->evidence();
        $missing = $valid;
        array_pop($missing);
        $duplicate = $valid;
        $duplicate[1] = $duplicate[0];
        $unknown = $valid;
        $unknown[0]['category'] = 'unknown-category';
        $malformedFinding = $valid;
        $malformedFinding[0]['finding_id'] = 'release.compatibility.wrong-category.fixture';
        $malformedEvidence = $valid;
        $malformedEvidence[0]['evidence_id'] = 'evidence.compatibility.wrong-category.fixture';
        $extraField = $valid;
        $extraField[0]['aggregate'] = 'patch';
        $missingField = $valid;
        unset($missingField[0]['evidence_id']);
        $unsupported = $valid;
        $unsupported[0]['classification'] = 'compatible';

        foreach ([null, [], $missing, $duplicate, $unknown, $malformedFinding, $malformedEvidence, $extraField, $missingField, $unsupported] as $candidate) {
            self::assertSame('invalid', $assessment->assess($candidate)['status']);
        }
    }

    /**
     * Covers indeterminate category evidence as a distinct governed stop.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_assess_does_not_derive_an_aggregate_from_indeterminate_evidence(): void
    {
        $evidence = $this->evidence();
        $evidence[3]['classification'] = 'indeterminate';
        $assessment = new CompatibilityAssessment()->assess($evidence);

        self::assertSame('indeterminate', $assessment['status']);
        self::assertNull($assessment['minimum_increment']);
    }

    /** @return list<array<string, string>> */
    private function evidence(): array
    {
        return array_map(
            static fn (string $category): array => [
                'category'       => $category,
                'finding_id'     => 'release.compatibility.'.$category.'.fixture',
                'evidence_id'    => 'evidence.compatibility.'.$category.'.fixture',
                'classification' => 'patch'
            ],
            CompatibilityAssessment::CATEGORIES
        );
    }
}
