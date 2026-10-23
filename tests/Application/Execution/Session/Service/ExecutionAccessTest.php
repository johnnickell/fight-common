<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Execution\Session\Service;

use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExecutionAccess::class)]
final class ExecutionAccessTest extends UnitTestCase
{
    public function test_that_access_copies_explicit_session_grants_and_preserves_empty_whole_and_numeric_projects(): void
    {
        $session = 'a';
        $grants = ExecutionAccess::create(['project' => [&$session, 'a'], 'empty' => [], 'all' => null, '123' => ['456']]);
        $session = 'changed';
        self::assertSame(['project' => ['a'], 'empty' => [], 'all' => null, 123 => ['456']], $grants->projects());
    }

    #[DataProvider('invalidAccess')]
    public function test_that_trusted_access_still_requires_finite_valid_scope(array $access): void
    {
        $this->expectException(DomainException::class);
        ExecutionAccess::create($access);
    }

    public function test_that_phase_grants_only_restrict_admitted_sessions_and_copy_numeric_reference_lists(): void
    {
        $id = 'phase';
        $access = ExecutionAccess::create(['project' => null, '123' => ['session']], ['project' => [&$id, 'phase'], '123' => []]);
        $id = 'changed';
        self::assertSame(['project' => ['phase'], 123 => []], $access->phaseGrants());
        self::assertSame([], ExecutionAccess::create(['project' => null])->phaseGrants());
    }

    #[DataProvider('invalidPhaseGrants')]
    public function test_that_phase_grants_require_finite_project_scoped_lists(array $grants): void
    {
        $this->expectException(DomainException::class);
        ExecutionAccess::create(['project' => null], $grants);
    }

    public static function invalidPhaseGrants(): iterable
    {
        yield [['other' => []]];
        yield [['project' => null]];
        yield [['project' => ['key' => 'phase']]];
        yield [['project' => array_fill(0, 101, 'phase')]];
        yield [['project' => ['invalid phase']]];
    }

    public function test_that_activity_restrictions_and_explicit_reference_grants_snapshot_producer_scoped_keys(): void
    {
        $id = 'producer/10000000-0000-4000-8000-000000000001';
        $key = $id;
        $access = ExecutionAccess::create(['project' => null, '123' => ['session']], [], ['project' => [&$id, $id], '123' => []], ['project' => null, '123' => [$key]]);
        $id = 'changed';
        self::assertSame(['project' => [$key], 123 => []], $access->activityGrants());
        self::assertSame(['project' => null, 123 => [$key]], $access->detailGrants());
        self::assertSame([], ExecutionAccess::create([])->detailGrants());
        self::assertSame(['project' => null], ExecutionAccess::create(['project' => null], activityGrants: ['project' => null])->activityGrants());
    }

    #[DataProvider('invalidObservationGrants')]
    public function test_that_activity_and_reference_access_require_finite_exact_project_scoped_keys(array $grants): void
    {
        foreach (['activityGrants', 'detailGrants'] as $field) {
            try { ExecutionAccess::create(['project' => null], ...[$field => $grants]); self::fail('Invalid grants must reject.'); }
            catch (DomainException $exception) { self::assertSame('Invalid execution observation access.', $exception->getMessage()); }
        }
    }

    public static function invalidObservationGrants(): iterable
    {
        yield [['other' => null]];
        yield [['project' => 'all']];
        yield [['project' => ['key' => 'producer/id']]];
        yield [['project' => array_fill(0, 101, 'producer/id')]];
        yield [['project' => [null]]];
        yield [['project' => ['producer/not-a-uuid']]];
        yield [['project' => ['bad producer/10000000-0000-4000-8000-000000000001']]];
    }

    public static function invalidAccess(): iterable
    {
        yield [array_fill_keys(range('a', 'q'), null)];
        yield [['invalid project' => null]];
        yield [['project' => 'session']];
        yield [['project' => ['key' => 'session']]];
        yield [['project' => array_fill(0, 101, 'session')]];
        yield [['project' => ['invalid session']]];
    }
}
