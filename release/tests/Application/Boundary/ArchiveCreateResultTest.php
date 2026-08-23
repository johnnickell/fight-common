<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application\Boundary;

use Fight\Release\Application\Boundary\ArchiveCreateResult;
use Fight\Release\Application\Boundary\ReleaseBoundaryOutcome;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/** Covers the classified archive-creation outcome value object. */
#[CoversClass(ArchiveCreateResult::class)]
#[CoversClass(ReleaseBoundaryOutcome::class)]
class ArchiveCreateResultTest extends UnitTestCase
{
    /**
     * Covers the successful archive-creation encoding.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_created_encodes_one_exact_completed_archive(): void
    {
        $result = ArchiveCreateResult::created('/tmp/fight-common-v1.3.0.zip', 'a'.str_repeat('b', 63));

        self::assertSame(ReleaseBoundaryOutcome::SUCCESS, $result->outcome);
        self::assertSame('/tmp/fight-common-v1.3.0.zip', $result->archivePath);
        self::assertSame('a'.str_repeat('b', 63), $result->sha256Digest);
        self::assertTrue($result->hasArchive());
    }

    /**
     * Covers the already-satisfied archive-creation encoding.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_already_satisfied_encodes_an_existing_archive_digest(): void
    {
        $result = ArchiveCreateResult::alreadySatisfied('c'.str_repeat('d', 63));

        self::assertSame(ReleaseBoundaryOutcome::ALREADY_SATISFIED, $result->outcome);
        self::assertNull($result->archivePath);
        self::assertSame('c'.str_repeat('d', 63), $result->sha256Digest);
        self::assertFalse($result->hasArchive());
    }

    /**
     * Covers the stopped archive-creation encoding and its non-archive identity.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_stopped_encodes_a_classified_boundary_stop_without_an_archive(): void
    {
        $result = ArchiveCreateResult::stopped(ReleaseBoundaryOutcome::REFUSAL);

        self::assertSame(ReleaseBoundaryOutcome::REFUSAL, $result->outcome);
        self::assertNull($result->archivePath);
        self::assertNull($result->sha256Digest);
        self::assertFalse($result->hasArchive());
    }
}