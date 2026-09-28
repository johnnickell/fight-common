<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Tool;

use Fight\Common\Adapter\Messaging\Command\Sync\CommandPipeline;
use Fight\Common\Adapter\Messaging\Query\QueryPipeline;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\Tool\McpToolAvailability;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolAuditMetadata;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolMetadataFilter;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Test\Common\Domain\Serialization\SampleCommand;
use Fight\Test\Common\Domain\Serialization\SampleQuery;
use Fight\Test\Common\Fixture\Mcp\ReadValueTool;
use Fight\Test\Common\Fixture\Mcp\WriteValueTool;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

#[CoversClass(McpToolMetadataFilter::class)]
final class McpToolMetadataFilterTest extends UnitTestCase
{
    public function test_that_scoped_filters_preserve_payload_identity_and_leave_original_envelopes_unchanged(): void
    {
        $audit = $this->mock(McpToolAuditMetadata::class);
        $info = $this->info();
        $audit->shouldReceive('fieldsFor')->once()->with($info)->andReturn(['consumer/actorId' => 'actor-1']);
        $filter = new McpToolMetadataFilter($audit, ['consumer/actorId']);
        $messages = [
            CommandMessage::create(new SampleCommand('value'))->withMeta(Meta::create(['existing/trace' => 'original'])),
            QueryMessage::create(new SampleQuery('value'))->withMeta(Meta::create(['existing/trace' => 'original'])),
        ];
        $seen = [];
        $result = $filter->invoke($info, function () use ($messages, $filter, &$seen): McpResult {
            foreach ($messages as $message) {
                $filter->process($message, function ($enriched) use (&$seen): void { $seen[] = $enriched; });
            }
            return McpResult::complete(['value' => 'safe']);
        });
        self::assertSame(['resultType' => 'complete', 'value' => 'safe'], $result->toArray());
        foreach ($seen as $index => $enriched) {
            self::assertSame($messages[$index]->id(), $enriched->id());
            self::assertSame($messages[$index]->payload(), $enriched->payload());
            self::assertSame($messages[$index]->timestamp(), $enriched->timestamp());
            self::assertSame('original', $enriched->meta()->get('existing/trace'));
            self::assertSame('echo', $enriched->meta()->get('fight.mcp/tool'));
            self::assertSame('actor-1', $enriched->meta()->get('consumer/actorId'));
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $enriched->meta()->get('fight.mcp/correlation'));
            self::assertSame(['existing/trace' => 'original'], $messages[$index]->meta()->toArray());
        }
        self::assertSame($seen[0]->meta()->get('fight.mcp/correlation'), $seen[1]->meta()->get('fight.mcp/correlation'));
        $filter->process($messages[0], fn($message) => self::assertSame($messages[0], $message));
    }

    public function test_that_real_command_and_query_pipelines_deliver_metadata_only_for_validated_invocation(): void
    {
        $filter = new McpToolMetadataFilter();
        $commandBus = $this->mock(SynchronousCommandBus::class);
        $queryBus = $this->mock(QueryBus::class);
        $commands = new CommandPipeline($commandBus);
        $queries = new QueryPipeline($queryBus);
        $commands->addFilter($filter);
        $queries->addFilter($filter);
        $received = [];
        $commandBus->shouldReceive('dispatch')->once()->andReturnUsing(function (CommandMessage $message) use (&$received): void {
            $received[] = $message;
        });
        $queryBus->shouldReceive('dispatch')->twice()->andReturnUsing(function (QueryMessage $message) use (&$received): array {
            $received[] = $message;
            return ['value' => 'safe'];
        });
        $availability = $this->mock(McpToolAvailability::class);
        $availability->shouldReceive('isAvailable')->twice()->andReturnTrue();
        $invoker = new McpToolInvoker(new McpToolRegistry([new ReadValueTool($queries), new WriteValueTool($commands)]), $availability, metadata: $filter);
        self::assertArrayHasKey('structuredContent', $invoker->invoke('value.read', (object) ['id' => 'item'])->toArray());
        self::assertArrayHasKey('structuredContent', $invoker->invoke('value.write', (object) ['id' => 'item'])->toArray());
        $queries->fetch(new SampleQuery('ordinary'));
        self::assertSame('value.read', $received[0]->meta()->get('fight.mcp/tool'));
        self::assertSame('value.write', $received[1]->meta()->get('fight.mcp/tool'));
        self::assertNotSame($received[0]->meta()->get('fight.mcp/correlation'), $received[1]->meta()->get('fight.mcp/correlation'));
        self::assertTrue($received[2]->meta()->isEmpty());
    }

    public function test_that_throwing_and_nested_scopes_are_cleaned_and_the_next_invocation_is_independent(): void
    {
        $filter = new McpToolMetadataFilter();
        try {
            $filter->invoke($this->info(), function () use ($filter): McpResult {
                return $filter->invoke($this->info(), fn(): McpResult => McpResult::complete([]));
            });
            self::fail('Nested scopes must reject.');
        } catch (DomainException $failure) {
            self::assertStringContainsString('nested', $failure->getMessage());
        }
        $message = QueryMessage::create(new SampleQuery());
        $filter->process($message, fn($received) => self::assertSame($message, $received));
        try {
            $filter->invoke($this->info(), fn() => throw new RuntimeException('private'));
        } catch (RuntimeException) {
            $filter->process($message, fn($received) => self::assertSame($message, $received));
        }
        self::assertSame(['resultType' => 'complete'], $filter->invoke($this->info(), fn(): McpResult => McpResult::complete([]))->toArray());
    }

    #[DataProvider('invalidFields')]
    public function test_that_unscoped_reserved_and_sensitive_field_declarations_reject(string $field): void
    {
        $this->expectException(DomainException::class);
        new McpToolMetadataFilter(allowedFields: [$field]);
    }

    public static function invalidFields(): iterable
    {
        foreach (['actorId', 'fight.mcp/tool', 'fight.mcp/correlation', 'consumer/password', 'consumer/accessToken', 'consumer/credentials', 'consumer/authorizationDecision', 'consumer/permissions', 'consumer/rawArguments', 'consumer/headers', 'consumer/payload'] as $field) {
            yield [$field];
        }
    }

    #[DataProvider('invalidMetadata')]
    public function test_that_invalid_extension_values_reject_before_invocation_and_do_not_leak(array $values): void
    {
        $audit = $this->mock(McpToolAuditMetadata::class);
        $audit->shouldReceive('fieldsFor')->once()->andReturn($values);
        $filter = new McpToolMetadataFilter($audit, ['consumer/actorId']);
        try {
            $filter->invoke($this->info(), fn() => self::fail('Invalid metadata must prevent invocation.'));
            self::fail('Invalid extension accepted.');
        } catch (DomainException) {
            $message = QueryMessage::create(new SampleQuery());
            $filter->process($message, fn($received) => self::assertSame($message, $received));
        }
    }

    public static function invalidMetadata(): iterable
    {
        yield [['fight.mcp/tool' => 'overwrite']];
        yield [['actorId' => 'unscoped']];
        yield [['consumer/notAllowed' => 'unknown']];
        foreach ([[], (object) [], null, INF, str_repeat('x', 257), "secret\nvalue", "\xff"] as $value) {
            yield [['consumer/actorId' => $value]];
        }
    }

    public function test_that_safe_scalar_extensions_and_audit_failures_preserve_scope_lifecycle(): void
    {
        $audit = $this->mock(McpToolAuditMetadata::class);
        $audit->shouldReceive('fieldsFor')->once()->andThrow(new RuntimeException('private'));
        $audit->shouldReceive('fieldsFor')->once()->andReturn(['consumer/count' => 1, 'consumer/ratio' => 0.5, 'consumer/flag' => true]);
        $filter = new McpToolMetadataFilter($audit, ['consumer/count', 'consumer/ratio', 'consumer/flag']);
        try {
            $filter->invoke($this->info(), fn() => self::fail('Audit failure must prevent invocation.'));
        } catch (RuntimeException $failure) {
            self::assertSame('private', $failure->getMessage());
        }
        $filter->invoke($this->info(), function () use ($filter): McpResult {
            $filter->process(QueryMessage::create(new SampleQuery()), function (QueryMessage $message): void {
                self::assertSame(1, $message->meta()->get('consumer/count'));
                self::assertSame(0.5, $message->meta()->get('consumer/ratio'));
                self::assertTrue($message->meta()->get('consumer/flag'));
            });
            return McpResult::complete([]);
        });
    }

    private function info(): McpToolInfo
    {
        return new McpToolInfo('echo', 'Echo', ['type' => 'object'], []);
    }
}
