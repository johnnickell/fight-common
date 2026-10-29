<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Execution;

use Fight\Common\Application\Mcp\Execution\ExecutionToolInput;
use Fight\Common\Application\Mcp\Execution\ExecutionToolOutput;
use Fight\Common\Application\Mcp\Execution\GetExecutionTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionPhasesTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionsTool;
use Fight\Common\Application\Mcp\Execution\ListPhaseActivityTool;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Execution\Observation\Data\ActivityView;
use Fight\Common\Application\Mcp\Execution\Phase\Data\PhasePage;
use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\Execution\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\Execution\SummarizeExecutionsTool;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\PhaseActivity;
use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Mcp\Execution\ObservationFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SummarizeExecutionsTool::class)]
#[CoversClass(ListExecutionsTool::class)]
#[CoversClass(GetExecutionTool::class)]
#[CoversClass(ListExecutionPhasesTool::class)]
#[CoversClass(ListPhaseActivityTool::class)]
#[CoversClass(ExecutionToolInput::class)]
#[CoversClass(ExecutionToolOutput::class)]
final class ExecutionToolsTest extends UnitTestCase
{
    public function test_that_tools_map_only_declared_fields_to_explicit_read_queries_and_preserve_safe_results(): void
    {
        $summary = new ExecutionSummary(0, ['unknown' => 0, 'succeeded' => 0, 'failed' => 0, 'cancelled' => 0], 0, 0, [], null);
        $cases = [
            [SummarizeExecutionsTool::class, ['projects' => ['p']], 'SummarizeExecutions', ['projects' => ['p'], 'filters' => []], $summary],
            [SummarizeExecutionsTool::class, ['projects' => ['p'], 'filters' => ['session_id' => 's']], 'SummarizeExecutions', ['projects' => ['p'], 'filters' => ['session_id' => 's']], $summary],
            [ListExecutionsTool::class, ['projects' => ['p']], 'ListExecutions', ['selection' => ['projects' => ['p'], 'filters' => []], 'page' => 1, 'per_page' => 100, 'orderings' => ['latest_recorded_at' => 'DESC']], new ExecutionPage([], 0, 1, 100)],
            [ListExecutionsTool::class, ['projects' => ['p'], 'filters' => ['consumer_reference' => 'c'], 'page' => 2, 'per_page' => 3, 'direction' => 'ASC'], 'ListExecutions', ['selection' => ['projects' => ['p'], 'filters' => ['consumer_reference' => 'c']], 'page' => 2, 'per_page' => 3, 'orderings' => ['latest_recorded_at' => 'ASC']], new ExecutionPage([], 0, 2, 3)],
            [GetExecutionTool::class, ['project_id' => 'p', 'session_id' => 's'], 'GetExecution', ['project_id' => 'p', 'session_id' => 's'], null],
            [ListExecutionPhasesTool::class, ['project_id' => 'p', 'session_id' => 's'], 'ListExecutionPhases', ['project_id' => 'p', 'session_id' => 's', 'page' => 1, 'per_page' => 100, 'filters' => [], 'orderings' => ['latest_recorded_at' => 'DESC']], new PhasePage([], 0, 1, 100)],
            [ListExecutionPhasesTool::class, ['project_id' => 'p', 'session_id' => 's', 'page' => 2, 'per_page' => 1, 'filters' => ['phase_id' => 'ph'], 'direction' => 'ASC'], 'ListExecutionPhases', ['project_id' => 'p', 'session_id' => 's', 'page' => 2, 'per_page' => 1, 'filters' => ['phase_id' => 'ph'], 'orderings' => ['latest_recorded_at' => 'ASC']], null],
            [ListPhaseActivityTool::class, ['project_id' => 'p', 'session_id' => 's', 'phase_id' => 'ph'], 'ListPhaseActivity', ['project_id' => 'p', 'session_id' => 's', 'phase_id' => 'ph', 'page' => 1, 'per_page' => 100, 'filters' => [], 'orderings' => ['recorded_at' => 'DESC']], new ActivityPage([], 0, 1, 100)],
            [ListPhaseActivityTool::class, ['project_id' => 'p', 'session_id' => 's', 'phase_id' => 'ph', 'page' => 3, 'per_page' => 4, 'filters' => ['kind' => 'app/call'], 'direction' => 'ASC'], 'ListPhaseActivity', ['project_id' => 'p', 'session_id' => 's', 'phase_id' => 'ph', 'page' => 3, 'per_page' => 4, 'filters' => ['kind' => 'app/call'], 'orderings' => ['recorded_at' => 'ASC']], null],
        ];
        foreach ($cases as [$class, $arguments, $queryName, $expected, $result]) {
            $bus = $this->mock(QueryBus::class);
            $bus->shouldReceive('fetch')->once()->withArgs(static function (Query $query) use ($queryName, $expected): bool {
                self::assertSame($queryName, (new \ReflectionClass($query))->getShortName());
                self::assertSame($expected, $query->toArray());
                return true;
            })->andReturn($result);
            $json = StrictJson::fromData($arguments);
            $output = (new $class($bus))->handle(new ApplicationData($json->properties()), new NullMcpProgressReporter());
            self::assertEquals(StrictJson::fromData($result?->toArray()), $output->structuredContent());
            self::assertSame(1048576, $output->maxEncodedResponseBytes());
            self::assertSame($output->structuredContent()->toString(), $output->text());
        }
    }

    public function test_that_direct_tool_calls_reject_forged_authority_and_invalid_domain_references_without_dispatch(): void
    {
        $bus = $this->mock(QueryBus::class);
        $bus->shouldNotReceive('fetch');
        $tool = new GetExecutionTool($bus);
        foreach ([['project_id' => 'p', 'session_id' => 's', 'access' => true], ['project_id' => '../p', 'session_id' => 's']] as $arguments) {
            try {
                $tool->handle(new ApplicationData($arguments), new NullMcpProgressReporter());
                self::fail('Invalid input must not reach the query bus');
            } catch (DomainException) { self::assertTrue(true); }
        }
    }

    public function test_that_safe_projection_overflow_rejects_instead_of_truncating_activity(): void
    {
        $observation = ObservationFixture::activity()->recorded(new \DateTimeImmutable('2026-10-22T12:00:00.000001Z'));
        $base = $observation->activity()->toArray();
        $usage = [];
        for ($i = 0; $i < 32; ++$i) { $usage[] = array_replace($base['usage'][0], ['metric' => 'app/metric'.$i]); }
        $activity = PhaseActivity::fromArray(array_replace($base, ['summary' => str_repeat('"', 512), 'usage' => $usage]));
        $view = new ActivityView($observation, $activity, []);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Execution Tool projection budget exceeded.');
        ExecutionToolOutput::create(new ActivityPage(array_fill(0, 100, $view), 100, 1, 100));
    }
}
