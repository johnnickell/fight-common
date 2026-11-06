# Compose Task, Notice and subscription storage

Physical table selection belongs to the Adapter/composition boundary. It changes **where existing operations run**,
not Task authority, Notice disclosure, subscriber inspection permission, retention, execution or delivery guarantees.
The three selections below cover the eight DBAL lifecycle tables; they add no Domain/Application configuration port,
request field, global registry, environment lookup or mandatory prefix.

## Complete defaults and SQL ownership

All class names in this table are under `Fight\Common\Adapter\Mcp\Persistence\{Task,Notice,Subscription}\Dbal`.
Each schema's `TABLES` constant remains its exact **default inventory**, not the current selection.

| Selection / constructor argument | Omitted default | Schema and runtime owner |
| --- | --- | --- |
| `DbalTaskTables::stateTable` | `fight_task_state` | `DbalTaskSchema`; `DbalTaskTransaction` version/serialization lock through actual commit |
| `DbalTaskTables::taskTable` | `fight_tasks` | `DbalTaskSchema`; `DbalTaskRepository` save/load, committed reads, input/cancellation state and whole-row removal |
| `DbalNoticeTables::stateTable` | `fight_notice_state` | `DbalNoticeSchema`; `DbalNoticeTransaction` allocation/lock, coherent `DbalNoticeRecoveryReader` namespace/high-water/loss reads and removal loss updates |
| `DbalNoticeTables::noticeTable` | `fight_notices` | `DbalNoticeSchema`; `DbalNoticeRepository` recording/retries/removal, `DbalNoticeRecords` reconstruction, `DbalNoticeReader` inspection and `DbalNoticeRecoveryReader` recovery |
| `DbalNoticeTables::relationshipTable` | `fight_notice_relationships` | `DbalNoticeSchema` cascading FK; repository writes, reconstruction/inspection/recovery joins and exact removal cascade |
| `DbalNoticeTables::comparisonTable` | `fight_notice_comparisons` | `DbalNoticeSchema` version/FK; repository retry comparison and removal cascade |
| `DbalSubscriptionTables::stateTable` | `fight_mcp_subscription_state` | `DbalSubscriptionSchema`; `DbalSubscriptionStore` version/capacity serialization lock, `DbalSubscriberReader` committed-version check |
| `DbalSubscriptionTables::subscriptionTable` | `fight_mcp_subscriptions` | `DbalSubscriptionSchema` expiry index; `DbalSubscriptionStore` active/total counts, open/renew/owned close and bounded cleanup; `DbalSubscriberReader` bounded summary/page scans |

The Task and Notice suppliers retain their [Task](durable-tasks.md#configurable-durable-storage) and
[Notice](safe-notices.md#explicit-physical-table-selection) contracts and focused qualification. This inventory
reconciles their composition with subscriptions, not a duplicate implementation or final ORM inventory claim.
Nonce/audit mappings remain separate ORM metadata; TASK-00202 owns their qualification and the final whole-package
inventory. Execution and Event Sourcing storage keep their own selections.

## One explicit composition across independent processes

Import `DbalTaskTables`, `DbalTaskSchema`, `DbalTaskTransaction` and `DbalTaskRepository` from the Task DBAL namespace;
`DbalNoticeTables`, `DbalNoticeSchema`, `DbalNoticeTransaction`, `DbalNoticeRepository`, `DbalNoticeReader` and
`DbalNoticeRecoveryReader` from Notice; and `DbalSubscriptionTables`, `DbalSubscriptionSchema`, `DbalSubscriptionStore`
and `DbalSubscriberReader` from Subscription.

```php
$taskTables = new DbalTaskTables(stateTable: 'app_task_state', taskTable: 'app_tasks');
$noticeTables = new DbalNoticeTables(
    stateTable: 'app_notice_state',
    noticeTable: 'app_notices',
    relationshipTable: 'app_notice_relationships',
    comparisonTable: 'app_notice_comparisons'
);
$subscriptionTables = new DbalSubscriptionTables(
    stateTable: 'app_subscription_state',
    subscriptionTable: 'app_subscriptions'
);

// Exclusive deployment only, on idle connections. Constructors/runtime do not install storage.
(new DbalTaskSchema($taskTables))->install($deploymentConnection);
(new DbalNoticeSchema($noticeTables))->install($deploymentConnection);
(new DbalSubscriptionSchema($subscriptionTables))->install($deploymentConnection);

$taskTransaction = new DbalTaskTransaction($taskConnection, $taskTables);
$taskRepository = new DbalTaskRepository($taskTransaction); // Also the committed Task read capability.
$noticeTransaction = new DbalNoticeTransaction($noticeWriteConnection, $noticeTables);
$noticeRepository = new DbalNoticeRepository($noticeTransaction);
$noticeReader = new DbalNoticeReader($noticeInspectionConnection, $noticeTables);
$noticeRecoveryReader = new DbalNoticeRecoveryReader($noticeRecoveryConnection, $noticeTables);
$subscriptionStore = new DbalSubscriptionStore($trackingConnection, tables: $subscriptionTables);
$subscriberReader = new DbalSubscriberReader(
    $subscriberInspectionConnection,
    'production-registration-db',
    tables: $subscriptionTables
);
```

These are consumer-owned connections to the intended database/schema, not new services or auto-wiring. Route existing
Commands/Queries with their current independent policies as documented by the component guides. Pass the same explicit
names to deployment, each producer/worker, every reader, HTTP process and cleanup scheduler. Separate PHP processes
cannot inherit an in-memory selection. Keep dedicated idle/committed-read and capability-owned transaction boundaries;
never hold a transaction across a live stream, disclosure guard lifetime or network wait.

Generic live changes use actual configured Notice recording and authorized recovery through the existing Query bus.
Task full-state delivery uses actual configured committed Task reads and its independently guarded byte handoff.
A mixed stream combines those sources without putting protected Task inputs/results/revisions into retained Notices
or technical subscriber rows. Task-only streams still need no Notice recovery. See
[live subscription composition](mcp-subscriptions.md#compose-the-complete-path) and
[Task disclosure](mcp-task-live.md#guarded-disclosure-boundary).

## Partial selection, validation and existing data

Each omitted argument retains only its own default. For example:

```php
$subscriptionTables = new DbalSubscriptionTables(subscriptionTable: 'app_subscriptions');
// stateTable deliberately remains fight_mcp_subscription_state.
(new DbalSubscriptionSchema($subscriptionTables))->install($deploymentConnection);
$subscriptionStore = new DbalSubscriptionStore(connection: $trackingConnection, maxActive: 64, tables: $subscriptionTables);
```

A partial override can intentionally share state or registration storage; it is **not** a fresh independent namespace.
Consumers own coherent pairings, capacity/retention coordination and distinct names across capabilities sharing a schema.
Installation rejects partially present selected storage rather than creating its missing counterpart beside existing
state. If an omitted default already exists, resolve that deployment composition explicitly; do not expect automatic
initialization, online repair or migration. `DbalSubscriptionSchema::addTo()` uses selected names; consumer-managed fresh
installation must initialize the selected singleton state row with `singleton: 1, version: 1`.

Shared `Adapter\Persistence\Doctrine\DbalTableName` accepts exactly `[A-Za-z_][A-Za-z0-9_]{0,62}` (1–63 ASCII characters),
preserves case and platform-quotes every selected identifier, including reserved words. Each capability rejects
case-insensitive table collisions. Values remain bound. Empty, qualified (`schema.table`), prequoted, whitespace,
Unicode, overlong and SQL-expression inputs reject without interpretation or fallback. These are trusted composition
inputs, never request-controlled routing or authorization. Database/schema choice and privileges remain connection
and operator concerns. Default indexes remain unchanged; renamed subscription registration tables receive a short
SHA-256-derived expiry index name, allowing selected and default tables to coexist on SQLite/PostgreSQL as well as MySQL.

Constructing a table selection performs no SQL. Adapter configuration performs no DDL, rename, copy or migration.
A changed full selection starts with empty
storage only after explicit installation, not recovered old Tasks, Notice history or subscriptions. Existing defaults
may coexist populated and are irrelevant to overridden counterparts. Missing/partial/incompatible selected storage
retains explicit storage/inspection failure boundaries; runtime never installs it or falls back to populated defaults.
Schema validation is the existing version/index/required-evidence check, not exhaustive drift repair. Old Notice
continuations/removal targets do not gain authority in a fresh namespace. Naming neither renews Task retention or
subscription expiry nor proves completion, notification receipt or processing.

## Package behavior evidence

`DbalConfiguredSubscriptionIntegrationTest` exercises full/default/partial choices on real SQLite, MySQL-compatible
and PostgreSQL storage, including poisoned default capacity/state, owner identity, renewal/no-op/closure/expiry,
cleanup, bounded inspection, separate-connection committed reads and explicit selected-schema failures.
`McpSubscriptionJourneyTest` retains default and configured variants of actual HTTP, independent producer/inspection
processes, native adapters, permission changes, abrupt/disconnect behavior, retained recovery and serialized lock waits.
`McpTaskLiveJourneyTest` similarly composes all eight configured suppliers, with an actual independent safe Notice
producer in mixed streams, guarded Task updates/input/cancellation, reconnect/current-state recovery, source/storage
faults and protected-state exclusion. Coexisting populated default counterparts remain unchanged. These are package
proofs, not deployed consumer authorization, saturated-network backpressure, replay, exactly-once delivery, client
receipt or successful worker execution guarantees. Required local verification and independent acceptance are recorded
by TASK-00201 and its ignored handoffs, not inferred from this guide.
