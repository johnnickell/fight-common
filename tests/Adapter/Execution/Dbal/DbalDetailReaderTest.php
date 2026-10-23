<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Fight\Common\Adapter\Execution\Dbal\DbalDetailReader;
use Fight\Common\Application\Execution\Detail\Data\CapturedDetail;
use Fight\Common\Application\Execution\Detail\Data\ContentChunk;
use Fight\Common\Application\Execution\Detail\Data\MessagePage;
use Fight\Common\Application\Execution\Detail\QueryHandler\GetCapturedDetailHandler;
use Fight\Common\Application\Execution\Detail\QueryHandler\ListCapturedMessagesHandler;
use Fight\Common\Application\Execution\Detail\Service\DetailAccess;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Execution\Detail\Query\ListCapturedMessages;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Test\Common\TestCase\Execution\DetailReadTestCase;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalDetailReader::class)]
#[CoversClass(DetailAccess::class)]
#[CoversClass(DetailSelection::class)]
#[CoversClass(CapturedDetail::class)]
#[CoversClass(ContentChunk::class)]
#[CoversClass(MessagePage::class)]
#[CoversClass(GetCapturedDetailHandler::class)]
#[CoversClass(ListCapturedMessagesHandler::class)]
#[CoversClass(GetCapturedDetail::class)]
#[CoversClass(ListCapturedMessages::class)]
final class DbalDetailReaderTest extends DetailReadTestCase
{
    public function test_that_corrupt_activity_and_detail_relationships_fail_closed_without_decoding_concealed_bodies(): void
    {
        $db = $this->database('sqlite'); $record = $this->writer($db);
        $record(ObservationFixture::session()); $record(ObservationFixture::phase());
        $activity = ObservationFixture::activity(); $record($activity);
        $detail = $this->captured($activity); $record($detail, ProtectedContent::create($detail->detail(), 'SECRET'));
        [$bus, $admission] = $this->detailBus($db); $selection = $this->selection($activity);
        $get = new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE);
        $list = new ListCapturedMessages($selection);
        foreach ([
            ['session' => array_replace($activity->session()->toArray(), ['project_id' => 'other'])],
            ['session' => array_replace($activity->session()->toArray(), ['session_id' => 'other']), 'phase' => array_replace($activity->phase()->toArray(), ['session_id' => 'other'])],
            ['session' => array_replace($activity->session()->toArray(), ['consumer_reference' => 'other'])],
            ['phase' => array_replace($activity->phase()->toArray(), ['name' => 'other'])],
            ['activity' => null, 'occurred_at' => '2026-10-20T12:00:00.123456Z']
        ] as $changes) {
            $invalid = Observation::fromArray(array_replace($activity->toArray(), $changes));
            $db->update('fight_execution_observations', ['payload' => $invalid->comparison()], ['observation_id' => $activity->id()->toString()]);
            $this->assertDetailFailure(static fn () => $bus->fetch($get));
            $this->assertDetailFailure(static fn () => $bus->fetch($list));
        }
        $db->update('fight_execution_observations', ['payload' => $activity->comparison()], ['observation_id' => $activity->id()->toString()]);
        foreach ([
            ['detail' => null, 'occurred_at' => '2026-10-20T12:00:00.123456Z'],
            ['detail' => array_replace($detail->detail()->toArray(), ['activity_id' => ObservationId::generate()->toString()])],
            ['phase' => array_replace($detail->phase()->toArray(), ['name' => 'other'])]
        ] as $changes) {
            $invalid = Observation::fromArray(array_replace($detail->toArray(), $changes));
            $db->update('fight_execution_observations', ['payload' => $invalid->comparison()], ['observation_id' => $detail->id()->toString()]);
            $this->assertDetailFailure(static fn () => $bus->fetch($get));
            $this->assertDetailFailure(static fn () => $bus->fetch($list));
        }
        $db->update('fight_execution_observations', ['payload' => $detail->comparison()], ['observation_id' => $detail->id()->toString()]);
        foreach (['null', '{', '{"version":2,"body":"SECRET"}', '{"version":1,"body":2}'] as $payload) {
            $db->update('fight_execution_contents', ['payload' => $payload], ['observation_id' => $detail->id()->toString()]);
            $this->assertDetailFailure(static fn () => $bus->fetch($get));
            $this->assertDetailFailure(static fn () => $bus->fetch($list));
        }
        // Current metadata-only permission never opens/decodes even a corrupt protected payload.
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => null]);
        self::assertNull($bus->fetch($get)->toArray()['chunk']);
        $admission->access = $this->allDetailAccess();
        $db->update('fight_execution_contents', ['payload' => json_encode(['version' => 1, 'body' => 'SECRET'])], ['observation_id' => $detail->id()->toString()]);
        $this->assertDetailFailure(static fn () => $bus->fetch(new GetCapturedDetail($selection, $detail->id(), DetailKind::MESSAGE, 7, 1)));
        $db->update('fight_execution_observations', ['version' => 2], ['observation_id' => $detail->id()->toString()]);
        $this->assertDetailFailure(static fn () => $bus->fetch($get));
        $admission->access = DetailAccess::create(['project' => null], metadataGrants: ['project' => ['other/'.$detail->id()->toString()]]);
        self::assertNull($bus->fetch($get));
        self::assertSame(0, $bus->fetch($list)->toArray()['total']);
    }
}
