<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Notice;

use Fight\Common\Application\Mcp\Notice\NoticeToolSchemas;
use Fight\Common\Application\Mcp\Notice\RecoverNoticesTool;
use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Mcp\Tool\NullMcpProgressReporter;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RecoverNoticesTool::class)]
final class RecoverNoticesToolTest extends UnitTestCase
{
    public function test_that_tool_dispatches_only_the_shared_query_and_preserves_result_shape_and_complete_response_bound(): void
    {
        $notice = new NoticeView(Notice::fromArray(NoticeFixture::data(['metadata' => []])), '2026-10-25T00:00:00Z');
        $result = new NoticeRecoveryBatch([$notice], 'uncertain', 'opaque');
        foreach ([['scopes' => ['scope']], ['scopes' => ['scope'], 'filters' => ['topic' => 'catalog/tools'], 'batch_size' => 1, 'scan_limit' => 2, 'continuation' => 'previous']] as $input) {
            $bus = $this->mock(QueryBus::class);
            $bus->shouldReceive('fetch')->once()->withArgs(static function (RecoverNotices $query) use ($input): bool {
                self::assertSame(['selection' => ['scopes' => ['scope'], 'filters' => $input['filters'] ?? []], 'continuation' => $input['continuation'] ?? null, 'batch_size' => $input['batch_size'] ?? 32, 'scan_limit' => $input['scan_limit'] ?? 100, 'start_at_current' => false], $query->toArray());
                return true;
            })->andReturn($result);
            $output = (new RecoverNoticesTool($bus))->handle(new ApplicationData(StrictJson::fromData($input)->properties()), new NullMcpProgressReporter());
            self::assertSame($result->toArray(), json_decode($output->text(), true));
            self::assertSame(1048576, $output->maxEncodedResponseBytes());
            self::assertTrue(McpToolSchemaValidator::matches($output->structuredContent(), StrictJson::fromObject(NoticeToolSchemas::OUTPUT)));
            self::assertTrue($output->structuredContent()->get('notices')[0]->get('metadata')->isObject());
        }
    }

    public function test_that_direct_calls_validate_exact_input_and_cannot_supply_identity_grants_or_unknown_filters(): void
    {
        $bus = $this->mock(QueryBus::class); $bus->shouldNotReceive('fetch');
        foreach ([0, 1048577] as $limit) { try { new RecoverNoticesTool($bus, $limit); self::fail('Invalid budget'); } catch (DomainException) { self::assertTrue(true); } }
        $tool = new RecoverNoticesTool($bus);
        foreach ([['access' => true], ['caller' => 'admin'], ['batch_size' => 33], ['scan_limit' => 101], ['continuation' => ''], ['filters' => ['body' => 'secret']], ['scopes' => ['../bad']]] as $bad) {
            $input = array_replace(['scopes' => ['scope']], $bad);
            try { $tool->handle(new ApplicationData(StrictJson::fromData($input)->properties()), new NullMcpProgressReporter()); self::fail('Invalid Tool input'); } catch (DomainException) { self::assertTrue(true); }
        }
    }
}
