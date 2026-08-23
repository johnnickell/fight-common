<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application\Boundary;

use Fight\Release\Application\Boundary\ReleasePackageEffectSet;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers the bounded packaging effect-set value object. */
#[CoversClass(ReleasePackageEffectSet::class)]
class ReleasePackageEffectSetTest extends UnitTestCase
{
    /**
     * Covers canonical identity derivation, path sorting, and public artifact data.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_effect_set_sorts_paths_and_exposes_canonical_artifact_data(): void
    {
        $effectSet = new ReleasePackageEffectSet(
            'd34db33fd34db33fd34db33fd34db33fd34db33f',
            '1.3.0',
            'fight-common-v1.3.0.zip',
            ['src/B.php', 'src/A.php'],
            ['vendor/b.php', 'vendor/a.php']
        );

        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/D', $effectSet->effectSetId);
        self::assertSame(['src/A.php', 'src/B.php'], $effectSet->includedPaths);
        self::assertSame(['vendor/a.php', 'vendor/b.php'], $effectSet->excludedPaths);

        $array = $effectSet->toArray();

        self::assertSame('fight-common.package-effect-set/v1', $array['schema_version']);
        self::assertSame($effectSet->effectSetId, $array['effect_set_id']);
        self::assertSame('d34db33fd34db33fd34db33fd34db33fd34db33f', $array['candidate_oid']);
        self::assertSame('1.3.0', $array['version']);
        self::assertSame('fight-common-v1.3.0.zip', $array['archive_name']);
        self::assertSame(['src/A.php', 'src/B.php'], $array['included_paths']);
        self::assertSame(['vendor/a.php', 'vendor/b.php'], $array['excluded_paths']);
    }

    /**
     * Covers identity equality for identical inputs and inequality after drift.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_effect_set_identity_matches_identical_effects_and_rejects_drift(): void
    {
        $first = new ReleasePackageEffectSet(
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            '1.3.0',
            'fight-common-v1.3.0.zip',
            ['src/A.php'],
            []
        );
        $identical = new ReleasePackageEffectSet(
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            '1.3.0',
            'fight-common-v1.3.0.zip',
            ['src/A.php'],
            []
        );
        $drifted = new ReleasePackageEffectSet(
            'a11ce0a1a11ce0a1a11ce0a1a11ce0a1a11ce0a1',
            '1.3.0',
            'fight-common-v1.3.0.zip',
            ['src/A.php', 'src/B.php'],
            []
        );

        self::assertSame($first->effectSetId, $identical->effectSetId);
        self::assertTrue($first->matches($identical));
        self::assertFalse($first->matches($drifted));
    }
}