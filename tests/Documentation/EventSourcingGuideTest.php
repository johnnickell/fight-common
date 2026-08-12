<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class EventSourcingGuideTest extends UnitTestCase
{
    public function test_that_the_documented_journey_executes_through_public_contracts(): void
    {
        $result = EventSourcingGuideExample::run();

        self::assertSame('Current name', $result['aggregate_name']);
        self::assertSame(2, $result['aggregate_version']);
        self::assertSame([], $result['pending_events']);
        self::assertSame(
            [
                $result['first_order_id'] => ['name' => 'Current name', 'global_position' => 2],
                $result['second_order_id'] => ['name' => 'Second order', 'global_position' => 3],
            ],
            $result['projection'],
        );
        self::assertSame(3, $result['projection_checkpoint']);
        self::assertSame(['Original name', 'Current name', 'Second order'], $result['dispatched_names']);
        self::assertSame(3, $result['publication_cursor']);
        self::assertSame(1, $result['publication_failures']);
    }

    public function test_that_the_guide_documents_the_public_persist_and_reload_path(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');
        $fixture = file_get_contents(__DIR__.'/EventSourcingGuideExample.php');

        self::assertIsString($guide);
        self::assertIsString($fixture);
        foreach ([
            '# Event Sourcing',
            '## Persist and reload an aggregate',
            'framework-free',
            'executable',
            'repository documentation tests',
            'stable aggregate name',
            'stable event names',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, preg_replace('/\s+/', ' ', $guide) ?? '');
        }

        foreach ([
            'final class Order extends AggregateRoot',
            'OrderPlaced => $this->whenOrderPlaced($event)',
            'OrderRenamed => $this->whenOrderRenamed($event)',
            'private function whenOrderPlaced(OrderPlaced $event): void',
            'private function whenOrderRenamed(OrderRenamed $event): void',
            'final readonly class OrdersEventMappingProvider implements EventMappingProvider',
            "new EventMapping('placed', OrderPlaced::class, 1)",
            "new EventMapping('renamed', OrderRenamed::class, 1)",
            '(new DbalEventStoreSchema())->install($connection);',
            '$eventMapper = new EventMapper([new OrdersEventMappingProvider()]);',
            '$eventStore = new DbalEventStore($connection, $eventMapper);',
            "new AggregateDefinition('orders', Order::class)",
            '$repository->save($firstOrder);',
            '$reloaded = $repository->find($firstOrderId);',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $fixture);
        }

    }

    public function test_that_php_examples_are_included_from_the_executable_fixture(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');
        $fixture = file_get_contents(__DIR__.'/EventSourcingGuideExample.php');
        $mkdocs = file_get_contents(dirname(__DIR__, 2).'/mkdocs.yml');

        self::assertIsString($guide);
        self::assertIsString($fixture);
        self::assertIsString($mkdocs);
        self::assertStringContainsString('pymdownx.snippets:', $mkdocs);
        self::assertStringContainsString('base_path: !relative $config_dir', $mkdocs);
        self::assertStringContainsString('restrict_base_path: true', $mkdocs);
        self::assertStringContainsString('check_paths: true', $mkdocs);

        preg_match_all(
            '/--8<-- "tests\/Documentation\/EventSourcingGuideExample\.php:([a-z0-9-]+)"/',
            $guide,
            $matches,
        );

        self::assertNotEmpty($matches[1]);
        self::assertSame(substr_count($guide, '```php'), count($matches[1]));

        foreach ($matches[1] as $region) {
            self::assertStringContainsString('// --8<-- [start:'.$region.']', $fixture);
            self::assertStringContainsString('// --8<-- [end:'.$region.']', $fixture);
        }
    }

    public function test_that_the_guide_documents_event_store_retry_and_history_hydration_contracts(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');

        self::assertIsString($guide);
        $normalizedGuide = preg_replace('/\s+/', ' ', $guide) ?? '';

        foreach ([
            'same message IDs occupy the intended consecutive stream positions immediately after the supplied expected version',
            'without writing again or comparing payload or metadata content',
            'partial, misplaced, reordered, or owned by another stream',
            'A stale expected version also fails closed',
            'Both stream and global reads',
            'complete sequential upcaster chain',
            'current `EventMessage` payload class',
            'unknown stable event name',
            'unsupported or newer schema version',
            'invalid or incomplete mapping',
            'never guess, skip, downcast, or best-effort hydrate',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $normalizedGuide);
        }
    }

    public function test_that_the_guide_documents_upgrade_compatibility_for_existing_cqrs_consumers(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');

        self::assertIsString($guide);
        $normalizedGuide = preg_replace('/\s+/', ' ', $guide) ?? '';

        foreach ([
            '## Upgrade compatibility',
            'Event Sourcing is additive',
            'signature-compatible behavioral change',
            'metadata copies',
            'derived same-ID envelopes',
            'Existing CQRS event triggering and dispatch remain supported',
            'Only events stored in the Event Store need mapper registration',
            'dispatch-only events do not',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $normalizedGuide);
        }
    }

    public function test_that_the_guide_documents_the_public_projection_and_rebuild_path(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');
        $fixture = file_get_contents(__DIR__.'/EventSourcingGuideExample.php');

        self::assertIsString($guide);
        self::assertIsString($fixture);

        foreach ([
            '## Project and rebuild read models',
            'strictly after',
            'successful skip',
            'before the checkpoint advances',
            'at-least-once',
            'Stop the projection worker.',
            'Clear or recreate the read model.',
            'Restart the projection worker.',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $guide);
        }

        foreach ([
            'final readonly class OrderSummaryProjector implements Projector',
            "return 'orders.order-summary';",
            'yield OrderPlaced::class;',
            'yield OrderRenamed::class;',
            'public function project(StoredEvent $event): void',
            '(new DbalProjectionCheckpointStoreSchema())->install($connection);',
            '$checkpointStore = new DbalProjectionCheckpointStore($connection);',
            '$projectionRunner = new ProjectionRunner($eventStore, $checkpointStore);',
            '$projectionRunner->run($orderSummaryProjector, 100);',
            '$checkpointStore->reset(\'orders.order-summary\');',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $fixture);
        }

        self::assertStringContainsString(
            'Global polling is prefix-stable: once position N is visible, no event at a lower position can '
            .'become visible later. Durable stores serialize global-position allocation inside the append '
            .'transaction. MySQL and PostgreSQL hold a transactional sequence-row lock through commit; SQLite '
            .'relies on serialized writer behavior. On MySQL, an event-table auto-increment key is not a safe '
            .'substitute because allocation order does not guarantee commit order.',
            preg_replace('/\s+/', ' ', $guide) ?? '',
        );
    }

    public function test_that_the_guide_documents_the_publication_and_failure_recording_path(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');
        $fixture = file_get_contents(__DIR__.'/EventSourcingGuideExample.php');

        self::assertIsString($guide);
        self::assertIsString($fixture);

        foreach ([
            '## Publish and diagnose committed events',
            'EventDispatchFailed',
            '`AllEvents`',
            'continues through every handler',
            'after the failure is recorded',
            'before the cursor is saved',
            'has no reset operation',
            'logger or recorder infrastructure failure',
            'first evidence',
            'no public query API',
            'verbatim',
            'safe to persist and log',
            'no automatic or targeted replay',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $guide);
        }

        foreach ([
            '(new DbalPublicationCursorStoreSchema())->install($connection);',
            '(new DbalPublicationFailureRecorderSchema())->install($connection);',
            '$cursorStore = new DbalPublicationCursorStore($connection);',
            '$durableFailureRecorder = new DbalPublicationFailureRecorder($connection);',
            'new LoggingPublicationFailureRecorder($durableFailureRecorder, new NullLogger())',
            '$publicationRunner = new EventPublicationRunner(',
            "'orders.subscribers'",
            '$publicationRunner->run(100);',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $fixture);
        }
    }

    public function test_that_the_guide_documents_migration_and_optional_symfony_integration(): void
    {
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/event-sourcing.md');
        $fixture = file_get_contents(__DIR__.'/EventSourcingGuideExample.php');
        $mkdocs = file_get_contents(dirname(__DIR__, 2).'/mkdocs.yml');

        self::assertIsString($guide);
        self::assertIsString($fixture);
        self::assertIsString($mkdocs);

        foreach ([
            '## Migrate durable names and event schemas',
            'stable aggregate name',
            'stable event names',
            'stable projector name',
            'stable publication name',
            'does not require an alias change or an upcaster',
            'one sequential upcaster per version',
            '`withMeta()` or `mergeMeta()`',
            'one metadata snapshot',
            'discard the released aggregate instance',
            'load a fresh aggregate',
            'Stop the projection worker.',
            'Clear or recreate the read model.',
            '$checkpointStore->reset(\'orders.order-summary\');',
            'Restart the projection worker.',
            '## Optionally collect mapping providers with Symfony',
            'private',
            'dependency-injected',
            'when the mapper is resolved',
            'manual construction remains supported',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $guide);
        }

        foreach ([
            'new EventMapper([new OrdersEventMappingProvider()])',
            'registerForAutoconfiguration(EventMappingProvider::class)',
            "->addTag('common.event_mapping_provider')",
            'new EventMappingProviderCompilerPass()',
            "->addMethodCall('registerProvider'",
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $fixture);
        }

        self::assertStringContainsString('- Event Sourcing: event-sourcing.md', $mkdocs);
    }

}
