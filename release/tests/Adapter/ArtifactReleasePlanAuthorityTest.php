<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\ArtifactReleasePlanAuthority;
use Fight\Release\Adapter\Fake\DeterministicReleaseBoundaryFake;
use Fight\Release\Application\Boundary\ReleasePlanAuthorityStatus;
use Fight\Release\Application\CanonicalJson;
use Fight\Release\Application\ReleasePlanFactory;
use Fight\Test\Common\TestCase\UnitTestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ArtifactReleasePlanAuthorityTest
 *
 * Covers current authority artifact validation and drift classification.
 */
#[CoversClass(ArtifactReleasePlanAuthority::class)]
final class ArtifactReleasePlanAuthorityTest extends UnitTestCase
{
    private string $output;

    /**
     * Creates one isolated runs output
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->output = dirname(__DIR__, 3).'/.runs/authority-test-'.bin2hex(random_bytes(8));
        mkdir($this->output, 0700, true);
    }

    /**
     * Removes the isolated runs output
     */
    protected function tearDown(): void
    {
        if (is_dir($this->output)) {
            foreach (new FilesystemIterator($this->output) as $path) {
                unlink($path->getPathname());
            }

            rmdir($this->output);
        }

        parent::tearDown();
    }

    /**
     * Covers verified authority and every independent drift classification
     */
    public function test_that_current_authority_changes_are_classified_independently(): void
    {
        $plan = $this->plan();

        foreach (['verified', 'support', 'evidence', 'compatibility', 'approval', 'invalid'] as $change) {
            $authority = $this->authority($plan);

            if ($change === 'support') {
                $authority['support_policy_identity'] = 'support-policy-2026-09';
            } elseif ($change === 'evidence') {
                $authority['evidence_manifest_digest'] = str_repeat('b', 64);
                $authority['release_approval_authority']['evidence_manifest_digest'] = str_repeat('b', 64);
            } elseif ($change === 'compatibility') {
                $authority['compatibility_exceptions'] = ['legacy-client-v1'];
                $authority['release_approval_authority']['compatibility_exception_ids'] = ['legacy-client-v1'];
            } elseif ($change === 'approval') {
                $authority['required_approvals'] = ['release-approval-002'];
                $authority['release_approval_authority']['approval_id'] = 'release-approval-002';
            } elseif ($change === 'invalid') {
                $authority['patch_exception_authorities'] = [['authority_id' => 'incomplete']];
            }

            $status = $this->adapter($authority)->revalidatePlanAuthority($plan);
            self::assertSame(match ($change) {
                'verified'      => ReleasePlanAuthorityStatus::VERIFIED,
                'support'       => ReleasePlanAuthorityStatus::SUPPORT_POLICY_DRIFT,
                'evidence'      => ReleasePlanAuthorityStatus::EVIDENCE_DRIFT,
                'compatibility' => ReleasePlanAuthorityStatus::COMPATIBILITY_DRIFT,
                'approval'      => ReleasePlanAuthorityStatus::APPROVAL_DRIFT,
                default         => ReleasePlanAuthorityStatus::UNCERTAIN
            }, $status, $change);
        }
    }

    /**
     * Proves set ordering in current authority is normalized before drift comparison
     */
    public function test_that_semantically_identical_reordered_authority_sets_remain_verified(): void
    {
        $candidate = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/release/fixtures/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $candidate['compatibility_exceptions'] = ['compatibility-zeta', 'compatibility-alpha'];
        $candidate['required_approvals'] = [
            'release-approval-zeta',
            'release-approval-001',
            'release-approval-alpha'
        ];
        $candidate['release_approval_authority']['compatibility_exception_ids'] = [
            'compatibility-zeta',
            'compatibility-alpha'
        ];
        $plan = new ReleasePlanFactory()->create($candidate);
        self::assertIsArray($plan);
        $authority = $this->authority($plan);
        $authority['compatibility_exceptions'] = array_reverse($authority['compatibility_exceptions']);
        $authority['required_approvals'] = array_reverse($authority['required_approvals']);
        $authority['release_approval_authority']['compatibility_exception_ids'] = array_reverse(
            $authority['release_approval_authority']['compatibility_exception_ids']
        );

        self::assertSame(
            ReleasePlanAuthorityStatus::VERIFIED,
            $this->adapter($authority)->revalidatePlanAuthority($plan)
        );
    }

    /**
     * Covers missing, forbidden, and noncanonical authority evidence
     */
    public function test_that_unverifiable_authority_is_uncertain(): void
    {
        $plan = $this->plan();
        $missingPath = $this->output.'/missing.json';
        $fake = new DeterministicReleaseBoundaryFake();
        $record = static fn ($effect, $outcome) => $fake->recordObservedEffect($effect, $outcome);
        self::assertSame(
            ReleasePlanAuthorityStatus::UNCERTAIN,
            new ArtifactReleasePlanAuthority($fake, $missingPath, dirname($this->output), $record)
                ->revalidatePlanAuthority($plan)
        );
        self::assertSame('uncertainty', $fake->effects()[4]['outcome']);
        foreach (['refusal', 'failure', 'uncertainty'] as $outcome) {
            $stopped = new DeterministicReleaseBoundaryFake();
            self::assertTrue($stopped->configureOutcome('filesystem.read', $outcome));
            self::assertSame(
                match ($outcome) {
                    'refusal' => ReleasePlanAuthorityStatus::REFUSED,
                    'failure' => ReleasePlanAuthorityStatus::FAILED,
                    default => ReleasePlanAuthorityStatus::UNCERTAIN
                },
                new ArtifactReleasePlanAuthority(
                    $stopped,
                    $missingPath,
                    dirname($this->output),
                    static fn ($effect, $boundaryOutcome) => $stopped->recordObservedEffect(
                        $effect,
                        $boundaryOutcome
                    )
                )->revalidatePlanAuthority($plan),
                $outcome
            );
            self::assertSame($outcome, $stopped->effects()[4]['outcome']);
        }

        foreach (['refusal', 'failure'] as $outcome) {
            $stopped = new DeterministicReleaseBoundaryFake();
            $expected = match ($outcome) {
                'refusal' => ReleasePlanAuthorityStatus::REFUSED,
                default => ReleasePlanAuthorityStatus::FAILED
            };
            self::assertTrue($stopped->configureOutcome('filesystem.inspect_runs_directory', $outcome));
            self::assertSame(
                $expected,
                new ArtifactReleasePlanAuthority(
                    $stopped,
                    $missingPath,
                    dirname($this->output),
                    static fn ($effect, $boundaryOutcome) => $stopped->recordObservedEffect(
                        $effect,
                        $boundaryOutcome
                    )
                )->revalidatePlanAuthority($plan),
                'directory '.$outcome
            );
        }

        self::assertSame(
            ReleasePlanAuthorityStatus::UNCERTAIN,
            new ArtifactReleasePlanAuthority($fake, '/tmp/forbidden.json', dirname($this->output), $record)
                ->revalidatePlanAuthority($plan)
        );
        file_put_contents($this->output.'/authority.json', "{\n");
        self::assertSame(
            ReleasePlanAuthorityStatus::UNCERTAIN,
            new ArtifactReleasePlanAuthority(
                $fake,
                $this->output.'/authority.json',
                dirname($this->output),
                $record
            )->revalidatePlanAuthority($plan)
        );

        foreach (["\n\n", "\r\n"] as $invalidTerminator) {
            file_put_contents(
                $this->output.'/authority.json',
                new CanonicalJson()->encode($this->authority($plan)).$invalidTerminator
            );
            self::assertSame(
                ReleasePlanAuthorityStatus::UNCERTAIN,
                new ArtifactReleasePlanAuthority(
                    $fake,
                    $this->output.'/authority.json',
                    dirname($this->output),
                    $record
                )->revalidatePlanAuthority($plan)
            );
        }
    }

    /** @param array<string, mixed> $authority */
    private function adapter(array $authority): ArtifactReleasePlanAuthority
    {
        $path = $this->output.'/authority.json';
        file_put_contents($path, new CanonicalJson()->encode($authority).PHP_EOL);
        $fake = new DeterministicReleaseBoundaryFake();

        return new ArtifactReleasePlanAuthority(
            $fake,
            $path,
            dirname($this->output),
            static fn ($effect, $outcome) => $fake->recordObservedEffect($effect, $outcome)
        );
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        $candidate = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/release/fixtures/plan-candidate.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $plan = new ReleasePlanFactory()->create($candidate);
        self::assertIsArray($plan);

        return json_decode(
            new CanonicalJson()->encode($plan),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array<string, mixed> $plan Revalidated immutable plan.
     *
     * @return array<string, mixed>
     */
    private function authority(array $plan): array
    {
        return [
            'compatibility_exceptions'    => $plan['compatibility_exceptions'],
            'evidence_manifest_digest'    => $plan['evidence_manifest_digest'],
            'patch_exception_authorities' => $plan['patch_exception_authorities'],
            'release_approval_authority'  => $plan['release_approval_authority'],
            'required_approvals'          => $plan['required_approvals'],
            'schema_version'              => 'fight-common.release-plan-authority/v1',
            'support_policy_identity'     => $plan['support_policy_identity']
        ];
    }
}
