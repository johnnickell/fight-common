<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Execution\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Fight\Common\Adapter\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Execution\Dbal\DbalExecutionReadSupport;
use Fight\Common\Application\Execution\Session\Data\ExecutionPage;
use Fight\Common\Application\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Execution\Session\Data\ExecutionView;
use Fight\Common\Application\Execution\Session\Exception\ExecutionReadFailed;
use Fight\Common\Application\Execution\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;
use Fight\Common\Domain\Execution\Usage\UsageTotal;
use Fight\Test\Common\TestCase\Execution\ExecutionReadTestCase;
use Fight\Test\Common\TestCase\Execution\ObservationFixture;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalExecutionReader::class)]
#[CoversClass(DbalExecutionReadSupport::class)]
#[CoversClass(ExecutionAccess::class)]
#[CoversClass(ExecutionPage::class)]
#[CoversClass(ExecutionReadFailed::class)]
#[CoversClass(ExecutionSelection::class)]
#[CoversClass(ExecutionSummary::class)]
#[CoversClass(ExecutionView::class)]
#[CoversClass(ListExecutions::class)]
#[CoversClass(SummarizeExecutions::class)]
#[CoversClass(ListExecutionsHandler::class)]
#[CoversClass(SummarizeExecutionsHandler::class)]
#[CoversClass(UsageTotal::class)]
final class DbalExecutionReaderTest extends ExecutionReadTestCase
{
    public function test_that_supported_server_platforms_request_bytewise_ordering_and_finite_batches(): void
    {
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            $db = $this->mock(Connection::class);
            $db->shouldReceive('getDatabasePlatform')->andReturn($platform);
            $db->shouldReceive('fetchAllAssociative')->once()->withArgs(static function (string $sql, array $parameters) use ($platform): bool {
                self::assertStringContainsString($platform instanceof PostgreSQLPlatform ? 'COLLATE "C"' : 'COLLATE utf8mb4_bin', $sql);
                self::assertStringContainsString('LIMIT 100', $sql);
                self::assertSame(['project', '', '', ''], $parameters);
                return true;
            })->andReturn([]);
            $summary = (new DbalExecutionReader($db))->summarize(ExecutionSelection::create(['project']), ExecutionAccess::create(['project' => null, 'unselected' => null, 'empty' => []]));
            self::assertSame(0, $summary->toArray()['executions']);
        }
    }

    public function test_that_excessive_usage_groups_fail_without_returning_partial_statistics(): void
    {
        $db = $this->database('sqlite');
        $record = $this->writer($db);
        $record(ObservationFixture::session());
        $record(ObservationFixture::phase());
        for ($i = 0; $i < 129; ++$i) {
            $activity = ObservationFixture::activity(['observation_id' => ObservationId::generate()->toString()])->toArray();
            $activity['activity']['usage'] = [UsageMeasurement::create('consumer/metric'.$i, 'unit', 'source', UsageCompleteness::COMPLETE, '1')->toArray()];
            $record(Observation::fromArray($activity));
        }
        [$bus] = $this->bus($db, ['project' => null]);
        $this->expectException(ExecutionReadFailed::class);
        $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])));
    }

    public function test_that_corrupt_stored_evidence_fails_safely_instead_of_fabricating_an_outcome(): void
    {
        $db = $this->database('sqlite');
        $this->writer($db)(ObservationFixture::session());
        [$bus] = $this->bus($db, ['project' => null]);
        foreach ([['version' => 2], ['version' => 1, 'payload' => 'null'], ['payload' => 'PROTECTED-SENTINEL']] as $corruption) {
            $db->update('fight_execution_observations', $corruption, ['producer_id' => 'instrumentation']);
            try { $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project']))); self::fail('Invalid retained data must fail.'); }
            catch (ExecutionReadFailed $failure) { self::assertStringNotContainsString('PROTECTED-SENTINEL', $failure->getMessage()); }
        }
        $db->update('fight_execution_observations', ['version' => 1, 'payload' => ObservationFixture::session()->comparison()], ['producer_id' => 'instrumentation']);
        $this->writer($db)(ObservationFixture::phase());
        $db->update('fight_execution_observations', ['version' => 2], ['observation_id' => ObservationFixture::phase()->id()->toString()]);
        $this->expectException(ExecutionReadFailed::class);
        $bus->fetch(new SummarizeExecutions(ExecutionSelection::create(['project'])));
    }
}
