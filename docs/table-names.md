# Database table names and ownership

Consumers select physical storage explicitly at the Adapter composition boundary. Common has **nineteen DBAL
and two ORM-mapped logical tables**, including internal state, sequence and lock-bearing tables. Alternate names
are not additional capabilities. There is no mandatory prefix, global registry, implicit environment lookup or
request-controlled table routing. Table selection grants no authentication, authorization, retention or delivery
permission.

## Complete defaults and configuration routes

Class names below are relative to `Fight\Common\Adapter`. Constructor argument names identify independently
selectable tables; omitted arguments keep their own exact defaults.

| Capability / selection owner | Argument or native mapping | Default table | Schema and runtime paths |
| --- | --- | --- | --- |
| Event Store — `EventSourcing\Dbal\DbalEventStoreTables` | `eventTable` | `event_store_events` | `DbalEventStoreSchema` definition/install; `DbalEventStore` append, retry, stream/global reads |
| Event Store — same selection | `globalPositionTable` | `event_store_global_position` | Same schema; singleton initialization and append serialization/allocation through commit |
| Checkpoints — `Persistence\Doctrine\DbalTableName` | `table:` on schema/store | `projection_checkpoints` | `DbalProjectionCheckpointStoreSchema`; `DbalProjectionCheckpointStore` load, monotonic save/race recovery, reset |
| Publication cursors — `Persistence\Doctrine\DbalTableName` | `table:` on schema/store | `publication_cursors` | `DbalPublicationCursorStoreSchema`; `DbalPublicationCursorStore` load, monotonic save/race recovery |
| Publication failures — `EventSourcing\Dbal\DbalPublicationFailureTables` | `failureTable` | `publication_failures` | `DbalPublicationFailureRecorderSchema`; `DbalPublicationFailureRecorder` atomic/idempotent parent writes |
| Publication failures — same selection | `handlerFailureTable` | `publication_handler_failures` | Same schema/recorder; handler inserts and selected-parent cascading FK |
| Execution — `Mcp\Persistence\Execution\Dbal\DbalObservationTables` | `schemaTable` | `fight_execution_schema` | `DbalObservationSchema`; `DbalRecordingTransaction` version validation and serialization lock |
| Execution — same selection | `sessionTable` | `fight_execution_sessions` | Same schema; `DbalSessionRepository`, session/phase/activity/detail read joins and subqueries |
| Execution — same selection | `phaseTable` | `fight_execution_phases` | Same schema; `DbalPhaseRepository`, relationship checks and all relevant inspection joins |
| Execution — same selection | `observationTable` | `fight_execution_observations` | Same schema; `DbalObservationRepository`, retries/reconstruction and inspection traversal/counts |
| Execution — same selection | `contentTable` | `fight_execution_contents` | Same schema; `DbalProtectedContentRepository` write/read/remove, content-presence/body inspection, observation-parent cascade |
| Tasks — `Mcp\Persistence\Task\Dbal\DbalTaskTables` | `stateTable` | `fight_task_state` | `DbalTaskSchema`; `DbalTaskTransaction` version/serialization lock |
| Tasks — same selection | `taskTable` | `fight_tasks` | Same schema; `DbalTaskRepository` save/load, committed reads, input/cancellation and whole-row removal |
| Notices — `Mcp\Persistence\Notice\Dbal\DbalNoticeTables` | `stateTable` | `fight_notice_state` | `DbalNoticeSchema`; `DbalNoticeTransaction` allocation/lock, coherent recovery state, removal/loss updates |
| Notices — same selection | `noticeTable` | `fight_notices` | Same schema; `DbalNoticeRepository`, `DbalNoticeRecords`, inspection/recovery and exact-incarnation removal |
| Notices — same selection | `relationshipTable` | `fight_notice_relationships` | Same schema; recording, reconstruction/inspection/recovery joins and removal cascade |
| Notices — same selection | `comparisonTable` | `fight_notice_comparisons` | Same schema; retry comparison/reconstruction and removal cascade |
| Subscriptions — `Mcp\Persistence\Subscription\Dbal\DbalSubscriptionTables` | `stateTable` | `fight_mcp_subscription_state` | `DbalSubscriptionSchema`; `DbalSubscriptionStore` version/capacity lock; subscriber-reader version check |
| Subscriptions — same selection | `subscriptionTable` | `fight_mcp_subscriptions` | Same schema; store counts/open/renew/close/bounded cleanup and `DbalSubscriberReader` summary/pages |
| Nonces — native ORM metadata for `Domain\Auth\Nonce` | `database/schema/Auth.Nonce.orm.xml` | `nonces` | Consumer schema setup; `Repository\Doctrine\DoctrineNonceRepository` raw-SQL consume/uniqueness and expired purge |
| Audit — native ORM metadata for `Domain\Observability\AuditEntry` | `database/schema/Observability.AuditEntry.orm.xml` | `audit_entries` | Consumer schema setup; `Repository\Doctrine\DoctrineAuditRepository` persist, actor/action/inclusive-time queries; consumer flush |

Event Sourcing schemas/stores are all under `Adapter\EventSourcing\Dbal`. Execution schemas/transactions and shared
`DbalExecutionReadSupport` live under `Adapter\Mcp\Persistence\Execution\Dbal`; repositories/readers live under their
`Session`, `Phase`, `Observation` and `Detail` DBAL branches. All four execution readers (`DbalExecutionReader`,
`DbalPhaseReader`, `DbalActivityReader`, `DbalDetailReader`) must receive the same selection. Task repositories derive
it from their exact transaction. Notice repositories do likewise; independent `DbalNoticeReader` and
`DbalNoticeRecoveryReader` require explicit matching selections. Subscriptions have no second inspection store.
No additional Event Sourcing retention API or cleanup table is implied: checkpoint reset resets progress, not history.

For detailed schema/SQL inventories and actual composition, see [Event Sourcing](components/event-sourcing/index.md#six-table-schema-and-sql-path-inventory),
[execution storage](execution-observations.md#five-table-sql-inventory) and
[Task/Notice/subscription storage](lifecycle-storage.md#complete-defaults-and-sql-ownership).

## Compose DBAL schema and runtime together

Pass a selection to both explicit deployment installation and every independently constructed runtime/reader.
For example, import the Event Sourcing classes from `Fight\Common\Adapter\EventSourcing\Dbal` and `DbalTableName`
from `Fight\Common\Adapter\Persistence\Doctrine`:

```php
$eventTables = new DbalEventStoreTables(eventTable: 'app_events', globalPositionTable: 'app_event_position');
$checkpointTable = new DbalTableName('app_checkpoints');
$cursorTable = new DbalTableName('app_publication_cursors');
$failureTables = new DbalPublicationFailureTables(
    failureTable: 'app_publication_failures',
    handlerFailureTable: 'app_handler_failures'
);

// Explicit deployment, not runtime construction or a data migration.
(new DbalEventStoreSchema($eventTables))->install($deploymentConnection);
(new DbalProjectionCheckpointStoreSchema($checkpointTable))->install($deploymentConnection);
(new DbalPublicationCursorStoreSchema($cursorTable))->install($deploymentConnection);
(new DbalPublicationFailureRecorderSchema($failureTables))->install($deploymentConnection);

$eventStore = new DbalEventStore($connection, $eventMapper, tables: $eventTables);
$checkpointStore = new DbalProjectionCheckpointStore($connection, table: $checkpointTable);
$cursorStore = new DbalPublicationCursorStore($connection, table: $cursorTable);
$failureRecorder = new DbalPublicationFailureRecorder($connection, tables: $failureTables);
```

Execution shares `DbalObservationTables` with `DbalObservationSchema`, `DbalRecordingTransaction` and each of the
four independent readers through their optional `tables:` argument; the four recording repositories inherit it
from the transaction. The [execution example](execution-observations.md) and
[lifecycle composition](lifecycle-storage.md#one-explicit-composition-across-independent-processes) show the exact
capability-owned connection/transaction boundaries. A selection does not substitute an ORM flush boundary for DBAL
transactions or permit caller-owned transactions.

Partial configuration changes only supplied names. For example,
`new DbalEventStoreTables(eventTable: 'app_events')` still selects `event_store_global_position`, and
`new DbalNoticeTables(comparisonTable: 'app_notice_comparisons')` still selects all three other Notice defaults.
This can deliberately share state/sequence/history; it is **not an independent namespace**. Consumers must keep
existing data and companion state coherent. Each process, worker, reader and cleanup scheduler needs the same
explicit choices; names do not propagate between processes automatically.

`DbalTableName` accepts exactly `[A-Za-z_][A-Za-z0-9_]{0,62}` (1–63 ASCII characters), preserves case and supplies
platform quoting, including for reserved words. Invalid, empty, prequoted, qualified, whitespace, Unicode,
overlong or expression-like DBAL names throw `InvalidArgumentException`, never fall back. Multi-table selections
reject case-insensitive collisions within their capability; consumers own cross-capability collisions and database
case/collation settings. Values stay bound. Database/schema selection and privileges remain connection/operator
concerns. **These new DBAL restrictions do not apply to native ORM metadata.**

## Native ORM authority for nonce and audit tables

Keep the existing XML mappings or supply consumer mappings through Doctrine's normal driver configuration. A
`SimplifiedXmlDriver` can load Common's `database/schema` directory with namespace prefix `Fight\Common\Domain`.
Register the `audit_entry_id` and `common_meta` DBAL types using
`Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType` and `MetaDataType`; the existing released
`Adapter\Doctrine` identities remain supported. `datetime_immutable` is Doctrine's native type. Table selection
does not change these types, field/column mappings, value encoding or timestamp precision.

A native `loadClassMetadata` listener is another normal consumer extension point. Register it **before metadata
is first loaded** and before constructing repositories. For example:

```php
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Fight\Common\Domain\Auth\Nonce;
use Fight\Common\Domain\Observability\AuditEntry;

$entityManager->getEventManager()->addEventListener([Events::loadClassMetadata], new class {
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        if ($metadata->getName() === Nonce::class) {
            $metadata->setPrimaryTable(['name' => 'app_nonces']);
        }
        if ($metadata->getName() === AuditEntry::class) {
            $metadata->setPrimaryTable(['name' => 'app_audit_entries']);
        }
    }
});
```

Omit either branch to retain that entity's default. Keep schema generation and runtime on the same metadata,
including independent EntityManagers/processes. Invalidate/version consumer metadata/query caches appropriately
when changing mappings; do not mutate live metadata after persisters or repositories have been constructed.
Common introduces no competing ORM table-name object or validator. Native quoted identifiers (Doctrine's backtick
mapping marker), schema mapping where the platform supports it and configured `QuoteStrategy` remain Doctrine's
responsibility. Nonce raw SQL resolves the table through that same configured quote strategy and connection
platform; audit DQL/persistence already uses native ORM resolution. This is table-name qualification, not a new
promise to customize every raw-SQL nonce column.

`DoctrineNonceRepository::consume()` inserts immediately with bound nonce/expiry values; it does not flush or
implicitly reject/delete expired values. A duplicate retains `NonceAlreadyConsumedException`; other storage
failures propagate normally. `purgeExpired()` deletes only entries with `expires_at < now` in the selected table.
Consumers own shared replay storage, timestamp policy and purge scheduling. A new table does not inherit old
replay protection.

`DoctrineAuditRepository::add()` schedules `persist()`; the consumer flushes/commits through its existing ORM
composition. Reads keep actor/action selection, pagination/orderings, typed `ResultSet<AuditEntry>` and inclusive
`getBetween(from, to)` (`>=`/`<=`) semantics. A new EntityManager can hydrate IDs, metadata and timestamps using
the existing types. Table naming does not introduce higher timestamp precision, audit admission/read permission
or an atomic audit/business transaction where one was not already composed.

## Installation, failures and no migration

Configuration/repository construction performs no DDL, copying, renaming or data transfer. Explicit DBAL installers
retain their existing differences: Event Sourcing creates missing selected tables/sequence state without exhaustive
validation or repair of existing columns/FKs; MCP installers require a coherent selected version-one namespace and
reject partially present/incompatible required evidence. See each capability's guide before installing beside old
storage. ORM schema setup is consumer-owned and explicit, not an automatic repository installer.

Changing mappings/selections alone leaves old tables and rows untouched. Explicitly installing a new full selection
creates empty storage, not migrated history, transferred replay memory, recovered progress or validated continuity.
Missing selected storage fails on use through existing database/capability exception boundaries, without runtime
creation or fallback to populated defaults. Do not infer successful migration from an empty query. Operational
renames/transfers, coordinated deployment, retained history, backup/recovery and database privileges remain consumer
responsibilities. Table naming changes no application permission, job outcome or delivery guarantee.

## Other storage owners and evidence limits

Framework/provider queues (Symfony transport, Laravel queue backend, CodeIgniter Queue) retain their own native
storage/configuration. Common delegates to injected provider contracts; it does not define those backend tables.
For example, CodeIgniter's prefixed `queue_jobs`/`queue_jobs_failed` in integration fixtures belong to that provider.
Consumer event read models and generic repository entities are consumer-owned. Fixture tables such as
`transaction_records`, `consumer_work`, `caller_work`, `task_removal_hold` and malformed/collision test tables
are not additional package persistence promises. Scheduler locks use files/`flock`, not a hidden DB table.

The source inventory was reconciled for TASK-00202 against the integrated TASK-00194–00201 suppliers. Event Sourcing
is owned by TASK-00194/00195/00196; execution recording and inspection/removal by TASK-00197/00198; lifecycle storage
by TASK-00199/00200/00201. Their canonical independent Review/QA and integration/gate identities are retained in the
TASK-00202 ignored evidence, not replaced by this inventory. They contain real SQLite/MySQL/PostgreSQL proof,
including selected state/locks and populated-default isolation, with their recorded finite-race/runtime limitations.

`DoctrineMappedStorageIntegrationTest` adds actual ORM/XML construction, default/full/individual overrides, quoted
tables, nonce replay/purge and audit flush/connection-reopen/queries on SQLite. It records executed SQL to reject
accidental default reads/writes, keeps matching poisoned/populated defaults unchanged and exercises missing storage
without migration/fallback. The nonce raw-SQL quoting repair additionally runs on MySQL and PostgreSQL, including a
native PostgreSQL schema. Existing meaningful unit coverage remains. These are package behavior checks, not release
certification, exhaustive native ORM configuration coverage or deployed-consumer qualification. TASK-00202 remains
subject to independent Review and QA; this guide issues no acceptance verdict.
