<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Execution\Dbal;

use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalExecutionReadSupport;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalPhaseReader;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ObservationView;
use Fight\Common\Application\Mcp\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Mcp\Execution\Phase\Data\PhaseView;
use Fight\Common\Application\Mcp\Execution\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Mcp\Execution\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Application\Mcp\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;
use Fight\Test\Common\TestCase\Mcp\Execution\ExecutionInspectionTestCase;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalExecutionReader::class)]
#[CoversClass(DbalExecutionReadSupport::class)]
#[CoversClass(DbalPhaseReader::class)]
#[CoversClass(ObservationView::class)]
#[CoversClass(PhasePage::class)]
#[CoversClass(PhaseView::class)]
#[CoversClass(ListExecutionPhasesHandler::class)]
#[CoversClass(ExecutionInspection::class)]
#[CoversClass(GetExecutionHandler::class)]
#[CoversClass(ExecutionAccess::class)]
#[CoversClass(GetExecution::class)]
#[CoversClass(ListExecutionPhases::class)]
final class DbalExecutionInspectionTest extends ExecutionInspectionTestCase
{
    public function test_that_corrupt_storage_identity_and_relationships_never_become_safe_metadata(): void
    {
        $db = $this->database('sqlite');
        $record = $this->writer($db);
        $session = ObservationFixture::session();
        $record($session);
        $phase = $this->phase('phase', null, 'agent', 1);
        $record($phase);
        [$bus] = $this->bus($db, ['project' => null]);
        foreach ([
            ['producer_id' => 'different'],
            ['observation_id' => ObservationId::generate()->toString()],
            ['session' => ['project_id' => 'other', 'session_id' => 'session', 'consumer_reference' => 'build:147']],
            ['phase' => null],
            ['phase' => array_replace($phase->phase()->toArray(), ['agent_id' => 'concealed-agent'])],
            ['session' => array_replace($phase->session()->toArray(), ['consumer_reference' => 'concealed-consumer'])]
        ] as $changes) {
            $corrupt = Observation::fromArray(array_replace($phase->toArray(), $changes));
            $db->update('fight_execution_observations', ['payload' => $corrupt->comparison()], ['observation_id' => $phase->id()->toString()]);
            try { $bus->fetch(new ListExecutionPhases('project', 'session')); self::fail('Corrupt relationships must fail.'); }
            catch (ExecutionReadFailed $failure) { self::assertSame('Execution evidence could not be read completely.', $failure->getMessage()); }
        }
        $db->update('fight_execution_observations', ['payload' => $phase->comparison()], ['observation_id' => $phase->id()->toString()]);
        foreach ([
            ['phase' => $phase->phase()->toArray()],
            ['session' => array_replace($session->session()->toArray(), ['consumer_reference' => 'concealed'])]
        ] as $changes) {
            $db->update('fight_execution_observations', ['payload' => Observation::fromArray(array_replace($session->toArray(), $changes))->comparison()], ['observation_id' => $session->id()->toString()]);
            try { $bus->fetch(new GetExecution('project', 'session')); self::fail('Corrupt session must fail.'); }
            catch (ExecutionReadFailed $failure) { self::assertSame('Execution evidence could not be read completely.', $failure->getMessage()); }
        }
    }

    public function test_that_excessive_model_metadata_rejects_instead_of_truncating_or_unbounded_output(): void
    {
        $db = $this->database('sqlite');
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        $record(ObservationFixture::phase());
        for ($i = 0; $i < 129; ++$i) {
            $data = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()])->toArray();
            $data['activity']['model_id'] = 'model:'.$i;
            $record(Observation::fromArray($data));
        }
        [$bus] = $this->bus($db, ['project' => null]);
        $this->expectException(ExecutionReadFailed::class);
        $bus->fetch(new ListExecutionPhases('project', 'session'));
    }
}
