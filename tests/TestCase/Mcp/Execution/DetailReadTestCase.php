<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Execution;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalDetailReader;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Execution\Detail\QueryHandler\GetCapturedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Detail\QueryHandler\ListCapturedMessagesHandler;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailAccess;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailReadAdmission;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Domain\Mcp\Detail\CaptureCompleteness;
use Fight\Common\Domain\Mcp\Detail\DetailCapture;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\OutputSegment;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Mcp\Observation\Event\ObservationRecorded;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Mcp\Session\SessionReference;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Utility\ClassName;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class DetailReadTestCase extends ExecutionReadTestCase
{
    #[DataProvider('databases')]
    public function test_that_real_commands_and_queries_reconstruct_all_content_kinds_in_bounded_byte_exact_chunks(string $backend): void
    {
        $db = $this->database($backend);
        $events = new SimpleEventDispatcher(); $eventCount = 0;
        $events->addHandler(ClassName::underscore(ObservationRecorded::class), static function () use (&$eventCount): void { ++$eventCount; });
        $record = $this->writer($db, $events);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        [$bus, $admission] = $this->detailBus($db);
        $selection = $this->selection($activity);
        foreach (DetailKind::cases() as $kind) {
            $detail = $this->captured($activity, $kind, CaptureCompleteness::PARTIAL);
            $body = str_repeat("α🙂\r\n", $kind === DetailKind::PROCESS_OUTPUT ? 2048 : 8192);
            $record($detail, ProtectedContent::create($detail->detail(), $body));
            $before = $this->detailState($db); $count = $eventCount;
            $offset = 0; $actual = ''; $chunks = 0;
            do {
                $query = new GetCapturedDetail($selection, $detail->id(), $kind, $offset, 4093);
                $envelope = QueryMessage::arrayDeserialize(QueryMessage::create($query)->arraySerialize());
                $result = $bus->dispatch($envelope)->toArray();
                $chunk = $result['chunk'];
                self::assertSame('base64', $chunk['encoding']);
                self::assertSame($offset, $chunk['offset']);
                self::assertLessThanOrEqual(4093, $chunk['bytes']);
                self::assertSame(strlen($body), $chunk['total_bytes']);
                self::assertSame($detail->detail()->contentType(), $result['reference']['capture']['content_type']);
                self::assertSame('partial', $result['reference']['capture']['completeness']);
                self::assertSame('2026-10-20T12:00:00.12Z', $result['reference']['occurred_at']);
                self::assertTrue($result['body_permitted']);
                $actual .= base64_decode($chunk['data'], true);
                $offset = $chunk['next_offset']; ++$chunks;
            } while ($offset !== null);
            self::assertSame($body, $actual);
            self::assertGreaterThan(1, $chunks);
            self::assertSame($before, $this->detailState($db));
            self::assertSame($count, $eventCount);
            self::assertFalse($db->isTransactionActive());
            $end = $bus->fetch(new GetCapturedDetail($selection, $detail->id(), $kind, strlen($body), 1))->toArray()['chunk'];
            self::assertSame('', $end['data']); self::assertSame(0, $end['bytes']); self::assertNull($end['next_offset']);
            if ($kind === DetailKind::PROCESS_OUTPUT) {
                self::assertSame(['stream' => 'stdout', 'index' => 3, 'offset' => 100], $result['reference']['capture']['segment']);
            }
        }
        self::assertGreaterThan(10, $admission->calls);
        $messages = $bus->dispatch(QueryMessage::arrayDeserialize(QueryMessage::create(new ListCapturedMessages($selection))->arraySerialize()));
        self::assertCount(1, $messages->messages());
        self::assertSame(1024, $messages->toArray()['messages'][0]['chunk']['bytes']);
        self::assertSame(1024, $messages->toArray()['messages'][0]['chunk']['next_offset']);
    }

    #[DataProvider('databases')]
    public function test_that_current_metadata_and_body_permissions_are_independent_of_timeline_references_and_prior_reads(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        $detail = $this->captured($activity); $record($detail, ProtectedContent::create($detail->detail(), 'SECRET-HUMAN-ONLY'));
        $otherActivity = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()]); $record($otherActivity);
        $other = $this->captured($otherActivity); $record($other, ProtectedContent::create($other->detail(), 'OTHER-ACTIVITY'));
        $session = SessionReference::fromStrings('other', 'session', 'other-consumer');
        $record(ObservationFixture::session(['session' => $session->toArray(), 'observation_id' => ObservationId::generate()->toString()]));
        $record(ObservationFixture::phase(['session' => $session->toArray(), 'observation_id' => ObservationId::generate()->toString()]));
        $foreignActivity = ObservationFixture::activity(['session' => $session->toArray(), 'observation_id' => ObservationId::generate()->toString()]); $record($foreignActivity);
        $foreign = $this->captured($foreignActivity); $record($foreign, ProtectedContent::create($foreign->detail(), 'OTHER-PROJECT'));
        [$bus, $admission] = $this->detailBus($db);
        $selection = $this->selection($activity); $query = new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE);
        self::assertSame('SECRET-HUMAN-ONLY', base64_decode($bus->fetch($query)->toArray()['chunk']['data'], true));
        $initial = $bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 0, 3))->toArray()['chunk'];
        self::assertSame(3, $initial['next_offset']);
        self::assertSame(1, $bus->fetch(new ListCapturedMessages($selection, 1, 1))->toArray()['total']);
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => [$this->detailKey($detail)]]);
        $agent = $bus->fetch($query)->toArray();
        self::assertFalse($agent['body_permitted']); self::assertNull($agent['chunk']);
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 3, 3))->toArray()['chunk']);
        self::assertSame('retained', $agent['reference']['availability']);
        self::assertStringNotContainsString('SECRET-HUMAN-ONLY', json_encode($agent));
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $other->id(), DetailKind::MESSAGE)));
        $admission->access = DetailAccess::create(['project' => null], bodyGrants: ['project' => null]);
        self::assertNull($bus->fetch($query)); self::assertNull($bus->fetch(new ListCapturedMessages($selection)));
        self::assertNull($bus->fetch(new ListCapturedMessages($selection, 2, 1)));
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => []], bodyGrants: ['project' => null]);
        self::assertNull($bus->fetch($query));
        $admission->access = $this->allDetailAccess();
        foreach ([
            $this->selection($activity, ['project_id' => 'other']),
            $this->selection($activity, ['session_id' => 'guessed']),
            $this->selection($activity, ['phase_id' => 'guessed']),
            $this->selection($activity, ['producer_id' => 'guessed']),
            $this->selection($activity, ['activity_id' => ObservationId::generate()->toString()])
        ] as $wrong) {
            self::assertNull($bus->fetch(new GetCapturedDetail($wrong, $detail->id(), DetailKind::MESSAGE)));
            self::assertNull($bus->fetch(new ListCapturedMessages($wrong)));
        }
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $other->id(), DetailKind::MESSAGE)));
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $foreign->id(), DetailKind::MESSAGE)));
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::TOOL_RESULT)));
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, ObservationId::generate(), DetailKind::MESSAGE)));
        $admission->access = DetailAccess::create(['project' => ['not-this-session']], metadataGrants: ['project' => null], bodyGrants: ['project' => null]);
        self::assertNull($bus->fetch($query));
        $admission->access = DetailAccess::create(['project' => null], ['project' => []], ['project' => null], ['project' => null]);
        self::assertNull($bus->fetch($query));
        $admission->access = $this->allDetailAccess();
        $db->executeStatement('DROP TABLE fight_execution_contents');
        $this->assertDetailFailure(static fn () => $bus->fetch($query));
        $this->assertDetailFailure(static fn () => $bus->fetch(new ListCapturedMessages($selection)));
        self::assertGreaterThan(10, $admission->calls);
    }

    #[DataProvider('databases')]
    public function test_that_empty_partial_expired_absent_and_disappearing_content_are_not_fabricated(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        [$bus] = $this->detailBus($db); $selection = $this->selection($activity);
        foreach (CaptureCompleteness::cases() as $completeness) {
            $detail = $this->captured($activity, completeness: $completeness);
            $captured = in_array($completeness, [CaptureCompleteness::COMPLETE, CaptureCompleteness::PARTIAL], true);
            $record($detail, ProtectedContent::create($detail->detail(), $captured ? '' : null));
            $query = new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE);
            $result = $bus->fetch($query)->toArray();
            self::assertSame($completeness->value, $result['reference']['capture']['completeness']);
            self::assertSame($captured ? 'retained' : $completeness->value, $result['reference']['availability']);
            if ($captured) { self::assertSame('', $result['chunk']['data']); self::assertNull($result['chunk']['next_offset']); }
            else { self::assertNull($result['chunk']); }
            $db->delete('fight_execution_contents', ['observation_id' => $detail->id()->toString(), 'producer_id' => $detail->producerId()]);
            $missing = $bus->fetch($query)->toArray();
            self::assertNull($missing['chunk']);
            self::assertSame($captured ? 'missing' : $completeness->value, $missing['reference']['availability']);
        }
        $detail = $this->captured($activity); $record($detail, ProtectedContent::create($detail->detail(), 'retained only until removed'));
        $first = $bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 0, 3))->toArray()['chunk'];
        self::assertSame(3, $first['next_offset']);
        $db->delete('fight_execution_contents', ['observation_id' => $detail->id()->toString()]);
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 3, 3))->toArray()['chunk']);
        $db->delete('fight_execution_observations', ['observation_id' => $detail->id()->toString()]);
        self::assertNull($bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE)));
    }

    #[DataProvider('databases')]
    public function test_that_live_message_pages_order_by_receipt_microseconds_and_full_uuid_not_occurrence(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        $ids = ['019a1234-5678-4abc-8def-123456789ab0', '019a1234-5678-4abc-8def-123456789ab1', '019a1234-5678-4abc-8def-123456789ab2'];
        $details = [];
        foreach ($ids as $i => $id) {
            $detail = $this->captured($activity, id: $id, occurrence: '2026-10-20T12:00:0'.(2 - $i).'Z');
            $record($detail, ProtectedContent::create($detail->detail(), 'message-'.$i)); $details[] = $detail;
        }
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.123456Z'");
        [$bus, $admission] = $this->detailBus($db); $selection = $this->selection($activity);
        $query = new ListCapturedMessages($selection, 1, 1);
        $first = $bus->fetch($query)->toArray();
        self::assertSame(3, $first['total']); self::assertSame($ids[2], $first['messages'][0]['reference']['observation_id']);
        self::assertSame('2026-10-20T12:00:00Z', $first['messages'][0]['reference']['occurred_at']);
        self::assertSame('2026-10-22T12:00:00.123456Z', $first['messages'][0]['reference']['recorded_at']);
        self::assertSame(1, $details[2]->recorded(Observation::timestamp('2026-10-22T12:00:00.123456Z'))->compareRecordingTo($details[1]->recorded(Observation::timestamp('2026-10-22T12:00:00.123456Z'))));
        self::assertSame($ids[1], $bus->fetch(new ListCapturedMessages($selection, 2, 1))->toArray()['messages'][0]['reference']['observation_id']);
        self::assertSame($ids[0], $bus->fetch(new ListCapturedMessages($selection, 1, 1, ['recorded_at' => 'asc']))->toArray()['messages'][0]['reference']['observation_id']);
        $db->update('fight_execution_observations', ['recorded_at' => '2026-10-22T12:00:00.123457Z'], ['observation_id' => $ids[0]]);
        self::assertSame($ids[0], $bus->fetch($query)->toArray()['messages'][0]['reference']['observation_id']);
        // A late-arriving older occurrence becomes the newest receipt, shifting the live page.
        $late = $this->captured($activity, occurrence: '2020-01-01T00:00:00.1Z'); $record($late, ProtectedContent::create($late->detail(), 'late'));
        self::assertSame($ids[0], $bus->fetch(new ListCapturedMessages($selection, 2, 1))->toArray()['messages'][0]['reference']['observation_id']);
        $db->delete('fight_execution_contents', ['observation_id' => $late->id()->toString()]);
        $db->delete('fight_execution_observations', ['observation_id' => $late->id()->toString()]);
        self::assertSame($ids[2], $bus->fetch(new ListCapturedMessages($selection, 2, 1))->toArray()['messages'][0]['reference']['observation_id']);
        $empty = $bus->fetch(new ListCapturedMessages($selection, 20, 32))->toArray(); self::assertSame(3, $empty['total']); self::assertSame([], $empty['messages']);
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => [$this->detailKey($details[1]), 'other/'.$ids[0]]], bodyGrants: ['project' => [$this->detailKey($details[1])]]);
        $page = $bus->fetch(new ListCapturedMessages($selection))->toArray();
        self::assertSame(1, $page['total']); self::assertTrue($page['messages'][0]['body_permitted']);
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => [$this->detailKey($details[0])]], bodyGrants: ['project' => [$this->detailKey($details[1])]]);
        self::assertNull($bus->fetch(new ListCapturedMessages($selection))->toArray()['messages'][0]['chunk']);
    }

    #[DataProvider('databases')]
    public function test_that_keyset_batches_count_all_permitted_messages_and_never_include_tool_rows(string $backend): void
    {
        $db = $this->database($backend); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        [$emptyBus] = $this->detailBus($db);
        self::assertSame(0, $emptyBus->fetch(new ListCapturedMessages($this->selection($activity)))->toArray()['total']);
        for ($i = 0; $i < 102; ++$i) {
            $detail = $this->captured($activity, $i === 50 ? DetailKind::TOOL_ARGUMENTS : DetailKind::MESSAGE);
            $record($detail, ProtectedContent::create($detail->detail(), str_repeat('m', 2048)));
        }
        $db->executeStatement("UPDATE fight_execution_observations SET recorded_at = '2026-10-22T12:00:00.123456Z'");
        [$bus] = $this->detailBus($db); $selection = $this->selection($activity);
        $desc = $bus->fetch(new ListCapturedMessages($selection, 4, 32))->toArray();
        self::assertSame(101, $desc['total']); self::assertCount(5, $desc['messages']);
        $asc = $bus->fetch(new ListCapturedMessages($selection, 1, 5, ['recorded_at' => 'ASC']))->toArray();
        self::assertSame(array_reverse(array_column(array_column($desc['messages'], 'reference'), 'observation_id')), array_column(array_column($asc['messages'], 'reference'), 'observation_id'));
        self::assertLessThan(262144, strlen(json_encode($bus->fetch(new ListCapturedMessages($selection))->toArray(), JSON_THROW_ON_ERROR)));
    }

    protected function detailBus(Connection $db): array
    {
        $admission = new class($this->allDetailAccess()) implements DetailReadAdmission {
            public int $calls = 0;
            public function __construct(public DetailAccess $access) {}
            public function currentAccess(DetailSelection $selection): DetailAccess { ++$this->calls; return $this->access; }
        };
        $reader = new DbalDetailReader($db); $router = new InMemoryQueryRouter();
        $router->registerHandler(GetCapturedDetailHandler::queryRegistration(), new GetCapturedDetailHandler($admission, $reader));
        $router->registerHandler(ListCapturedMessagesHandler::queryRegistration(), new ListCapturedMessagesHandler($admission, $reader));
        return [new RoutingQueryBus($router), $admission];
    }

    protected function allDetailAccess(): DetailAccess
    {
        return DetailAccess::create(['project' => null], metadataGrants: ['project' => null], bodyGrants: ['project' => null]);
    }

    protected function captured(Observation $activity, DetailKind $kind = DetailKind::MESSAGE, CaptureCompleteness $completeness = CaptureCompleteness::COMPLETE, ?string $id = null, string $occurrence = '2026-10-20T12:00:00.12Z'): Observation
    {
        $capture = DetailCapture::create($activity->id(), $kind, $kind === DetailKind::TOOL_ARGUMENTS ? 'application/json' : 'text/plain', $completeness, $kind === DetailKind::PROCESS_OUTPUT ? OutputSegment::create('stdout', 3, 100) : null);
        return Observation::forDetail($id === null ? ObservationId::generate() : ObservationId::fromString($id), $activity->producerId(), $activity->session(), $activity->phase(), ObservationOccurrence::fromString($occurrence), $capture);
    }

    protected function selection(Observation $activity, array $changes = []): DetailSelection
    {
        return DetailSelection::fromArray(array_replace(['project_id' => $activity->session()->projectId(), 'session_id' => $activity->session()->sessionId(), 'phase_id' => $activity->phase()->phaseId(), 'producer_id' => $activity->producerId(), 'activity_id' => $activity->id()->toString()], $changes));
    }

    protected function detailKey(Observation $detail): string
    {
        return $detail->producerId().'/'.$detail->id()->toString();
    }

    protected function detailState(Connection $db): array
    {
        return array_map(static fn ($table) => $db->fetchAllAssociative('SELECT * FROM '.$table), DbalObservationSchema::TABLES);
    }

    protected function assertDetailFailure(callable $read): void
    {
        try { $read(); self::fail('Invalid evidence must not be a successful empty result.'); }
        catch (ExecutionReadFailed $failure) {
            self::assertSame('Execution evidence could not be read completely.', $failure->getMessage());
            self::assertNotNull($failure->getPrevious());
            self::assertStringNotContainsString('SECRET', $failure->getMessage());
        }
    }
}
