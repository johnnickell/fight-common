<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Execution\Dbal;

use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalActivityReader;
use Fight\Common\Application\Mcp\Execution\Detail\Data\DetailReference;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityView;
use Fight\Common\Application\Mcp\Execution\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;

use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\Query\ListPhaseActivity;
use Fight\Common\Domain\Mcp\Usage\UsageCompleteness;
use Fight\Common\Domain\Mcp\Usage\UsageMeasurement;
use Fight\Test\Common\TestCase\Mcp\Execution\ActivityReadTestCase;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalActivityReader::class)]
#[CoversClass(ActivityPage::class)]
#[CoversClass(ActivityView::class)]
#[CoversClass(DetailReference::class)]
#[CoversClass(ListPhaseActivityHandler::class)]
#[CoversClass(ExecutionAccess::class)]
#[CoversClass(ListPhaseActivity::class)]
final class DbalActivityReaderTest extends ActivityReadTestCase
{
    public function test_that_corrupt_activity_or_detail_scope_cannot_be_projected_as_safe_metadata(): void
    {
        $db = $this->database('sqlite'); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        [$bus, $admission] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        foreach ([
            ['session' => array_replace($activity->session()->toArray(), ['project_id' => 'other'])],
            ['session' => array_replace($activity->session()->toArray(), ['session_id' => 'other']), 'phase' => array_replace($activity->phase()->toArray(), ['session_id' => 'other'])],
            ['session' => array_replace($activity->session()->toArray(), ['consumer_reference' => 'concealed'])],
            ['phase' => array_replace($activity->phase()->toArray(), ['agent_id' => 'concealed'])]
        ] as $changes) {
            $db->update('fight_execution_observations', ['payload' => Observation::fromArray(array_replace($activity->toArray(), $changes))->comparison()], ['observation_id' => $activity->id()->toString()]);
            $this->assertReadFailure(static fn () => $bus->fetch($query));
        }
        $detail = $this->detail($activity, CaptureCompleteness::COMPLETE);
        $db->update('fight_execution_observations', ['payload' => $detail->comparison(), 'observation_id' => $detail->id()->toString()], ['observation_id' => $activity->id()->toString()]);
        $this->assertReadFailure(static fn () => $bus->fetch($query));
        $db->delete('fight_execution_observations', ['observation_id' => $detail->id()->toString()]);
        $record($activity);
        $record($detail, ProtectedContent::create($detail->detail(), 'PROTECTED-SENTINEL'));
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        foreach ([
            ObservationFixture::phase(['observation_id' => $detail->id()->toString()]),
            Observation::fromArray(array_replace($detail->toArray(), ['detail' => array_replace($detail->detail()->toArray(), ['activity_id' => ObservationId::generate()->toString()])]))
        ] as $invalid) {
            $db->update('fight_execution_observations', ['payload' => $invalid->comparison()], ['observation_id' => $detail->id()->toString()]);
            $this->assertReadFailure(static fn () => $bus->fetch($query));
        }
    }

    public function test_that_reference_limit_is_exact_and_concealed_excess_is_not_counted(): void
    {
        $db = $this->database('sqlite'); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        for ($i = 0; $i < 32; ++$i) {
            $detail = $this->detail($activity, CaptureCompleteness::ABSENT);
            $record($detail, ProtectedContent::create($detail->detail(), null));
        }
        [$bus, $admission] = $this->activityBus($db);
        $query = new ListPhaseActivity('project', 'session', 'phase');
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => null]);
        self::assertCount(32, $bus->fetch($query)->activities()[0]->details());
        $last = $this->detail($activity, CaptureCompleteness::EXPIRED);
        $record($last, ProtectedContent::create($last->detail(), null));
        $this->assertReadFailure(static fn () => $bus->fetch($query));
        $admission->access = ExecutionAccess::create(['project' => null], detailGrants: ['project' => [$this->key($last), 'other/'.$detail->id()->toString()]]);
        self::assertCount(1, $bus->fetch($query)->activities()[0]->details());
        self::assertSame('expired', $bus->fetch($query)->toArray()['activities'][0]['details'][0]['availability']);
    }

    public function test_that_encoded_response_limit_rejects_instead_of_returning_partial_results(): void
    {
        $db = $this->database('sqlite'); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $data = ObservationFixture::activity()->toArray();
        $data['activity']['usage'] = [];
        for ($i = 0; $i < 32; ++$i) {
            $data['activity']['usage'][] = UsageMeasurement::create('provider/'.str_repeat('m', 80).$i, str_repeat('u', 128), str_repeat('p', 128), UsageCompleteness::COMPLETE, str_repeat('9', 128))->toArray();
        }
        for ($i = 0; $i < 100; ++$i) { $data['observation_id'] = ObservationId::generate()->toString(); $record(Observation::fromArray($data)); }
        [$bus] = $this->activityBus($db);
        self::assertCount(1, $bus->fetch(new ListPhaseActivity('project', 'session', 'phase', 1, 1))->activities());
        $this->assertReadFailure(static fn () => $bus->fetch(new ListPhaseActivity('project', 'session', 'phase')));
    }

    private function assertReadFailure(callable $read): void
    {
        try { $read(); self::fail('Invalid evidence must fail closed.'); }
        catch (ExecutionReadFailed $failure) {
            self::assertSame('Execution evidence could not be read completely.', $failure->getMessage());
            self::assertStringNotContainsString('PROTECTED-SENTINEL', $failure->getMessage());
            self::assertNotNull($failure->getPrevious());
        }
    }
}
