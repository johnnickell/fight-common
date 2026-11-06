# Durable asynchronous Task state

This opt-in shared lifecycle persists and retrieves asynchronous operation state. It does **not** independently
enable or advertise MCP Tasks, change `McpTool::handle()` or run a worker. Explicit [MCP Tasks](mcp-tasks.md)
composition now supplies complete guarded start/get/update/cancel activation and HTTP reconnect proof.
[Cooperative cancellation](task-cancellation.md) extends this same row/lock with durable intent, independent authority
and actual tasks/cancel handling; intent never forces a terminal outcome.
[Durable Task input](task-input.md) extends this same lifecycle with retained forms/confirmation, partial admission,
protected observation and single-use continuation. Independently authorized explicit removal is supplied below. A Task is neither a planning TASK,
consumer job, Workflow nor execution authority. Polling has no Subscriptions, Mercure or Event Store dependency.

## Model classification and value contracts

| Type | Responsibility and classification |
| --- | --- |
| `Domain\Mcp\Task\Task` | Ordinary aggregate root: exact trusted scope plus TaskId select its retained lifetime; it owns invariant-preserving transitions, retry/version fences, input and cancellation. Not a value or event-sourced base subclass. |
| `TaskId` | Final readonly `ValueObject` implementing `Identifier`: exactly 32 lowercase ASCII hexadecimal bytes, not a UUID. `generate()` creates random bytes; `fromString()` rejects rather than trims/case-folds. Same-type equality/hash/string/JSON use the exact ID; `compareTo()` is bytewise and rejects other types. Scope remains a separate required selection component, not part of the lexical ID or an authority grant. |
| `TaskDefinition` | Final readonly `ValueObject`: original ID, scope, producer and complete retained operation contract. Replacing it with an equal definition preserves the same original context; it is not the mutable Task aggregate. |
| `TaskState` | Final readonly `ValueObject`: complete status-specific description, including nullable message and input/result/error distinctions, not the lifetime or concurrency fence. |
| `TaskInputBatch` | Final readonly `ValueObject`: caller, expiry, confirmation mode and original named public/private contracts before issuance. Equal batches may be issued as different groups. |
| `TaskSelection` | Final readonly `ValueObject`: exact scope and ordered list of 1–100 distinct `TaskId` values. Order is retained for per-member admission and removal facts, not silently sorted as a set. |
| `TaskInputs` and retained groups | Nested lifecycle bookkeeping, not values: Task-scoped namespace, lifetime high-water counter and identified groups with durable answers, retirement and one-time consumption. Immutable replacement does not erase entity identity or make consumption comparable by value. Groups remain embedded under their issued first-request key; no extra aggregate/repository is introduced. |
| Commands, Queries and events | Intent, selection and reference-only facts implementing their messaging contracts, not ValueObject merely because readonly. Typed IDs become exact strings explicitly in `toArray()` and reconstruct in `fromArray()`. |
| Application Query views and `McpTaskHandle` | Use-case/protocol data, not Domain values. Internal `TaskProjection` builds the existing structured `StrictJson` Query view; the handle carries a typed ID but grants neither disclosure nor execution. |

Domain constructors, repository/committed-read methods and Task admission ports take `TaskId`; database columns,
message arrays and MCP `taskId` remain the same strings. Convert incoming trusted selections with
`TaskId::fromString($bytes)` and encode outward with `$taskId->toString()`. Repository hydration compares both exact
scope and ID value, not PHP instance identity. No wrapper is added for every caller/producer/form-key string.

The four composite values provide `fromString()`/`toString()` over their existing structured JSON fields, plus
same-concrete-type equality/hash from ValueObject. Their `jsonSerialize()` deliberately emits structures, never
an embedded JSON string. Definition/state retain `fromArray()`/`toArray()`; input batches retain `fromData()`/`data()`;
selection adds explicit message `fromArray()`/`toArray()`. Malformed/incoherent string reconstruction raises
DomainException. Nested object/list, absent/explicit-null, empty/null message, integer/float, form/confirmation and
result/error distinctions survive. Source ordering inside retained JSON contracts/payloads is significant, agreeing
with exact encoded-content retries; these values do not canonicalize or sort arbitrary JSON. Selection equality
includes supplied order and scope. Equality and hash reconstruction are not authorization, redaction or a public-safe
serializer: definitions and batches contain protected context. Definition/state/batch value strings use shortest
round-trip float precision regardless of ambient `serialize_precision`, restoring that setting before returning.
The same lossless encoding governs construction/reconstruction byte checks: operation 8,192 bytes, state 32,768
bytes and batch 24,576 bytes. Ambient rounding cannot admit an oversized stable value, and expanded float spelling
cannot reject an in-bound one. This stabilizes equality, hashes and reconstruction without changing explicit
wire/storage/retry encoders or outer `json_encode()` options/precision. Shared low-level StrictJson remains unchanged.

## Explicit composition

Use a dedicated idle DBAL connection to an owned SQLite, MySQL or PostgreSQL database. During exclusive deployment,
`(new DbalTaskSchema())->install($connection)` creates or validates the version-one namespace. The schema also offers
`addTo($schema, $platform)` for consumer migration composition; initialize the selected state table (default
`fight_task_state`) with singleton/version `1/1` when composing manually. MySQL uses InnoDB and `utf8mb4_bin`;
partial DDL is an operator recovery condition.
There are no development-format migrations, automatic cleanup or schema changes to other Common capabilities.

Construct these explicit collaborators (names below are under `Fight\Common`):

- `$transactionalUnitOfWork = new Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction($connection)`, implementing the
  existing Application `TransactionalUnitOfWork` around `Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork`.
  The Task wrapper owns namespace locking/schema validation and Task-specific failure translation, not persistence.
- `$taskRepository = new Adapter\Mcp\Persistence\Task\Dbal\DbalTaskRepository($transactionalUnitOfWork)`, implementing Domain
  `Mcp\Task\TaskRepository` (`getById`, `save`, `remove`) without transaction inheritance or connection access.
  Its constructor binds it to the exact Task boundary and connection; an unrelated active transaction cannot admit writes.
- `$committedTaskReader = $taskRepository`, supplied through Application `Mcp\Task\Service\CommittedTaskReader`.
  This narrow `read(scope, id): ?Task` capability supplies protected coherent committed aggregates for existing Queries
  and cancellation acknowledgment. The same persistence adapter reconstructs the aggregate; no second store,
  reporting methods, disclosure policy or protocol projection is introduced. It rejects active transactions even
  when the mutation repository is owned; Query callers never switch between storage modes.
- Independent request/producer-scoped `TaskCreationAdmission`, `TaskUpdateAdmission`, `TaskReadAdmission` and
  `TaskRemovalAdmission`.
  Each decision uses trusted consumer context, never a submitted producer label as a credential. Mutation admission
  occurs after acquiring the namespace lock, before identity lookup, including retries. Read admission precedes lookup
  and is checked again immediately before disclosure. Required `TaskReadAdmission::comparisonScope(scope, id)` binds
  a stable trusted caller/visibility epoch across processes and rotates on any visibility discontinuity. See
  [live/read revision privacy and comparison](mcp-task-live.md#state-bound-revisions-and-consumer-merge-contract).
- `TaskPayloadPolicy`, which validates the retained **original** operation contract without executing it and supplies
  a fresh whole-state disclosure decision. Status/message, input, result and error each require permission and safe
  projection where consumer policy requires. Returning false withholds the entire view; never substitute an empty result.
- Existing `EventDispatcher` and a trusted `Closure(): int` server epoch-millisecond clock. For example,
  `static fn (): int => (int) floor(microtime(true) * 1000)`. Synchronize clocks; logical expiry depends on that clock.

Each handler owns its named use case and receives only its operation-specific capabilities. Construct and register:

```php
$createTaskHandler = new CreateTaskHandler(
    $taskRepository, $transactionalUnitOfWork, $taskCreationAdmission, $taskPayloadPolicy, $eventDispatcher, $clock
);
$recordTaskStateHandler = new RecordTaskStateHandler(
    $taskRepository, $transactionalUnitOfWork, $taskUpdateAdmission, $taskPayloadPolicy, $eventDispatcher, $clock
);
$removeTasksHandler = new RemoveTasksHandler(
    $taskRepository, $transactionalUnitOfWork, $taskRemovalAdmission, $eventDispatcher
);
$getTaskHandler = new GetTaskHandler($committedTaskReader, $taskReadAdmission, $taskPayloadPolicy, $clock);
```

Register the first three with the existing Command router and `GetTaskHandler` with the Query router. Handler
namespaces are `Application\Mcp\Task\CommandHandler` and `Application\Mcp\Task\QueryHandler`.
Commands explicitly own transactions and post-commit facts; Queries inject neither a write repository nor a
transaction boundary and never initiate cleanup or renew TTL. No broad lifecycle coordinator or replacement
facade is required. Their Domain payloads are `Domain\Mcp\Task\Command\{CreateTask,RecordTaskState}` and
`Domain\Mcp\Task\Query\GetTask`; removal uses `Domain\Mcp\Task\Command\RemoveTasks`. See `tests/TestCase/Mcp/Task/TaskFixture.php` for executable explicit bus composition.
The existing bus signatures remain `CommandBus::execute(): void`, `QueryBus::fetch()` and `EventDispatcher::trigger()`.

A typical application sequence, after composing `$commandBus` and `$queryBus`, is:

```php
use Fight\Common\Domain\Mcp\Task\Command\CreateTask;
use Fight\Common\Domain\Mcp\Task\Command\RecordTaskState;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Mcp\Task\TaskId;
use Fight\Common\Domain\Mcp\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

$taskId = TaskId::generate(); // Generate before the void Command; possession grants nothing.
$taskDefinition = TaskDefinition::create($taskId, 'project:example', 'producer:example', StrictJson::fromObject([
    'kind' => 'tool',
    'name' => 'example.operation',
    'revision' => 'retained-contract-v1',
    'output_schema' => (object) ['type' => 'object']
]));
$commandBus->execute(new CreateTask($taskDefinition, TaskState::create('working'), 60000, 500));
$taskView = $queryBus->fetch(new GetTask('project:example', $taskId));
// Only trusted producer evidence establishes this result. No operation is re-executed here.
$taskResult = StrictJson::fromObject([
    'content' => [(object) ['type' => 'text', 'text' => 'Finished']],
    'structuredContent' => (object) ['ok' => true],
    'isError' => false
]);
$commandBus->execute(new RecordTaskState(
    'project:example', $taskId, 'producer:example', 1, TaskState::create('completed', result: $taskResult)
));
```

Consumer jobs, operation arguments, dispatch, business authorization and external effects remain consumer-owned.
Keep any arguments/contract revisions needed for later validation in bounded original context or in a deliberately
qualified custom policy; never silently resolve a result against a changed catalog. The reusable concrete
`ToolTaskPayloadPolicy` validates the captured schema directly, with no Tool invocation or current-definition lookup.
It requires a nonempty name/revision, `kind: tool` and an object `output_schema` in the retained context.

Its supported complete Tool result is an object containing `content` (a list of text items), explicit Boolean
`isError` and optional `structuredContent`. Non-error results require structured content matching the retained
bounded Tool schema profile. `isError: true` is still **completed**, not failed. Other content types require an
explicit appropriate `TaskPayloadPolicy`; unsupported data rejects, never truncates or silently changes meaning.
Failed state contains a JSON-RPC execution error with an integer code and nonempty message. This concrete policy
accepts only configured exact code/constant-public-message pairs, default `-32603 / Internal error`, and no error
`data`. It never maps arbitrary exceptions or business failures. Domain error data is available to deliberately
qualified alternative policies. All policy validation diagnostics are concealed behind fixed `TaskRejected`.

Input-required state stores 1–16 keyed outstanding request objects. The base invariant supports only `form` and
`confirmation`; the concrete policy validates a bounded message and the existing flat elicitation schema profile.
[The input lifecycle](task-input.md) owns issuance, lifetime keys, partial responses and separately authorized
single-use continuation, using this same state/version/transaction rather than a second store. Raw producer
`RecordTaskState` cannot create/replace input contracts or clear unanswered forms with `working`. Production inputs and results must already be consumer-projected safe evidence where appropriate; JSON/schema
validation alone is not redaction. The concrete policy's required disclosure closure decides current safety/access
for the entire view, not just its final body. No default public-read policy exists.

## Configurable durable storage

`Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables` explicitly selects both physical tables independently:

| Constructor parameter | Default | Responsibility |
| --- | --- | --- |
| `stateTable` | `fight_task_state` | Schema version and namespace serialization lock |
| `taskTable` | `fight_tasks` | Complete retained Task, input, cancellation and retry/comparison payload |

Pass the same selection to the deployment schema and Task transaction. The repository and its committed reader
inherit that exact selection from the transaction; they accept no second potentially inconsistent configuration.
For example (all names below are under `Fight\Common`):

```php
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskRepository;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskSchema;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTables;
use Fight\Common\Adapter\Mcp\Persistence\Task\Dbal\DbalTaskTransaction;

$tables = new DbalTaskTables(stateTable: 'app_task_state', taskTable: 'app_tasks');
// Explicit exclusive deployment only, never inside a handler or request.
(new DbalTaskSchema(tables: $tables))->install($connection);
$transactionalUnitOfWork = new DbalTaskTransaction(connection: $connection, tables: $tables);
$taskRepository = new DbalTaskRepository(transactionalUnitOfWork: $transactionalUnitOfWork);
$committedTaskReader = $taskRepository;
```

`new DbalTaskTables(stateTable: 'app_task_state')` retains `fight_tasks`;
`new DbalTaskTables(taskTable: 'app_tasks')` retains `fight_task_state`. Omitting the selection preserves both
exact defaults and existing constructor/named arguments. `DbalTaskSchema::TABLES` remains the **default inventory**,
not a configured-table accessor; use `$tables->stateTable()->name()` / `$tables->taskTable()->name()` for selected
logical names. `DbalTaskTransaction::tables()` exposes the exact adapter-only composition selection, without adding
configuration to Domain/Application contracts. Give every participating connection/producer/worker the same selection.
All Commands, protected Queries, polling and bounded retries continue through their existing handlers and policies.

Names use the shared Adapter `DbalTableName`: exactly `[A-Za-z_][A-Za-z0-9_]{0,62}` (1–63 ASCII bytes), with no trimming,
qualification, quoting syntax, whitespace or implicit prefixes. The two names must be distinct even ignoring case
for portable MySQL composition. Invalid names/collisions raise `InvalidArgumentException` before database effects.
Reserved words and mixed case are safely platform-quoted; data remains bound separately. Consumers retain connection/
schema selection, privileges and database naming/case constraints. Common reads no environment/global naming settings
and accepts no table names from MCP requests.

Changing a selection **does not migrate, rename, copy or recover old data**, renew TTL, clear input/cancellation,
change a revision, authorize access or stop a job. Runtime operations never install tables or fall back to defaults.
A changed Task table can be empty while the old one remains populated; this is not successful migration. An explicit
installer creates both selected tables only when neither exists, or validates the existing complete selection.
A partial selection (including one intentionally retained default already present while its new counterpart is absent)
rejects as incomplete: operators must explicitly reconcile/complete storage under exclusive deployment, not expect
an automatic half-install or data transfer. Incompatible version/payload storage also rejects. Retention, permissions,
rollback/uncertainty, actual commit and independent repository/transaction composition remain unchanged.

## State, concurrency and retries

Creation starts `working`, private producer version 1. Each accepted producer update replaces state/payload/polling advice atomically,
advances the private Task-local version by one and preserves original definition, creation time and TTL. Both `working` and
`input_required` can move to any supported state; `completed`, `failed` and `cancelled` cannot be replaced. Timestamps
are server-owned, nondecreasing per Task, and not concurrency fences. Cancelled means reported producer evidence,
not client disconnect, silence, expiry or a cancellation request.

One version-row write lock serializes mutations across the namespace through **actual commit**, including concurrent
creates, repeats and removal. This favors simple correctness over write throughput; consumers own bounded SQL
lock/statement timeouts. There is no automatic hidden retry. Current reads are one coherent committed row, no history
or snapshot across requests. Do not share the connection concurrently or mutate transaction state in policy callbacks.
Callbacks must be local bounded validation, never execute consumer jobs inside the transaction.

Identity is the exact case-sensitive `(scope, 32 lowercase hexadecimal application ID)` tuple; the definition binds
one producer and original operation context. Use `TaskId::generate()` (128 random bits), not JSON-RPC IDs, job IDs or planning IDs.
Creation retains a SHA-256 digest of its original encoded definition/state/TTL/polling tuple. Same-content creation
retries are admitted no-ops even after progress, while conflicting retained creation reuse raises `TaskConflict`.
Encoding is deterministic for the submitted ordered JSON representation: reordered object properties are different
content for this contract. Do not serialize/hydrate a retry through a lossy codec.

Producer updates specify the observed positive expected version. Exactly matching **immediately previous** updates
are no-ops using one retained SHA-256 digest of producer/expected-version/state/polling. The same expected version with
changed content, an older retry after another accepted update, stale progress/input or a competing terminal outcome
conflicts. Recheck permission and original contract even on matching retries. Retain only creation/latest-transition
digests, no hidden historical payload copies or update-ID ledger. These are state-store deduplication rules, not
business idempotency or exactly-once consumer effects. Version exhaustion rejects new transitions but not a provable
matching retry. Digests are protected comparison material and never projected publicly.

Creation, latest-update comparison and payload live in the same retained row. Physical removal ends those guarantees;
no hidden tombstone or historical comparison archive remains. A producer update to an absent tuple always rejects;
it never inserts, restores a body or treats absence as completion. A separately **admitted CreateTask** after removal,
even a delayed matching creation retry, creates a new working lifetime at private version 1 with new creation time/TTL
and a new incomparable public revision scope.
There is no permanent identity memory to recognize that old creation. Consumers must use fresh random IDs for new
operations and bound creation retries to their known retained lifetime. If they deliberately reuse a removed tuple,
old producer version fences may match the new lifetime: quiesce/revoke old producers and reconcile uncertain work
before any such explicit recreation. Common does not promise cross-lifetime deduplication or job cancellation.

## Independently authorized bounded removal

```php
use Fight\Common\Domain\Mcp\Task\Command\RemoveTasks;
use Fight\Common\Domain\Mcp\Task\TaskSelection;

$commandBus->execute(new RemoveTasks(TaskSelection::fromIds('project:example', [$taskId])));
// A fresh GetTask under independent read permission now returns null, with no asserted absence reason.
```

`TaskSelection::fromIds()` accepts one exact scope and a list of **1–100 distinct TaskId values**. Scope/ID bounds are the same
as creation. Empty, associative, duplicate, non-TaskId (including raw string) and over-limit selections reject with
DomainException before database work; nothing is silently dropped or truncated. `fromArray()` is the explicit message
representation boundary and reconstructs validated TaskId values from string members. The same immutable selection is used by the store
and reference-only fact. One request is one atomic all-or-nothing transaction, not 100 independently committed actions.
Database work is bounded by the namespace lock/check and at most 100 indexed whole-row DELETE statements, with no
payload loading, eligibility scan or retained archive. Consumers supply finite lock/statement timeouts.

Inside that transaction, after obtaining the same serialization lock as create/update, Common calls
`TaskRemovalAdmission::allowsRemoval(scope, id)` afresh for every selected identity **before any existence check or
deletion**. False or a throwing admission rejects the entire selection with fixed TaskRejected, without a private
cause. This check runs for missing/repeated selections too. Creation/update/read grants, ID possession, TTL expiry
and submitted administrator labels confer no removal permission. Consumer composition owns trusted context,
eligibility, holds and policy changes; callbacks must remain bounded/local and must not release transaction ownership.
A denied later member cannot leave earlier members deleted. Cross-scope denial discloses no presence or payload.

Authorized missing members are successful no-ops. Mixed present/missing selections remove every present member;
an entirely missing repeat commits no data change and emits no fact. The void Command returns no per-ID existence
receipt. Storage failure is explicit, never apparent successful partial removal. Removal is independent of logical
TTL and terminal status: working, outstanding input, completed result, failed error, cancelled and expired/unlimited
rows can all be removed under consumer permission. It does not force a terminal outcome, stop a job, undo external
effects or authorize compensation. Corrupt payloads can be removed without decoding them.

Whole-row deletion removes original context, current state/input/result/error, private input contracts/answers/
consumption/lifetime-key bookkeeping, cancellation intent, retention metadata and both comparison digests atomically. `fight_task_state` retains only the constant schema-version lock row, not identity/payload history.
This is logical database deletion, not a promise of forensic erasure from engine pages, transaction logs, backups,
replicas, consumer caches or consumer-authored event storage. Consumers own those retention boundaries.

Racing reads see one coherent committed retained row or null, never a partially cleared payload. A read already in
flight may return its previously read coherent state; removal cannot retract bytes. Update-first/removal-second
removes that committed outcome. Removal-first/update-second rejects the update without resurrection. Create-first
is removed; removal-first followed by **separately authorized creation** starts the explicitly described new lifetime.
An unsupported enclosing transaction rejects before admission/writes and leaves unrelated caller work untouched.
Pre-commit failure rolls the entire selection back; uncertain commit/response loss needs a fresh authorized Query,
not an invented deletion receipt. Retry always rechecks current permission, including after revocation.

After actual commit, one `Domain\Mcp\Task\Event\TaskDataRemoved` contains only `scope` and the supplied-order IDs of rows
actually deleted. Missing identities produce no false facts; there is no reason/status/body/digest/history ledger.
References can themselves need consumer disclosure policy. `CommittedTaskDispatchFailed::event()` now carries either
TaskChanged or TaskDataRemoved. Later publisher/subscriber failure cannot restore deleted data or become rollback;
matching removal retries do not replay facts. Loss between commit and dispatch is possible: no automatic outbox,
replay or permanent removal receipt is promised. Never copy protected data into removal diagnostics or event payloads.

## Durability and failures

`DbalTaskTransaction` rejects caller transactions, savepoint composition, closed boundaries and dirty-read isolation
before admission or writes. It implements the separately injected `TransactionalUnitOfWork`; the repository does not.
Its reusable DBAL mechanism rejects auto-commit-off composition without changing connection mode. An unrelated DBAL
or ORM transaction, or a different Task boundary on the same connection, cannot enlist repository writes.
Do not add an outer transactional Command filter. Rejected caller work is never committed or rolled back.
All state is persisted before successful Command return. Backend durability configuration remains an operator
responsibility; this is not a transaction with a job system, automatic submission or execution acknowledgment.

- A known rollback leaves prior coherent state and permits an explicit fresh admitted retry.
- `TaskStorageFailed::outcomeUncertain()` is true for uncertain begin/commit/rollback; the boundary closes. A commit
  may already have succeeded, including response loss. Use a fresh connection and currently authorized Query, then
  the documented bounded retry contract. Never infer absence or duplicate an external effect from the exception.
- `TaskChanged` carries only scope, ID and Task-local version after actual commit. No status, original context,
  result/error/input bodies or generic notice-feed recording. References may still require consumer disclosure policy.
- `CommittedTaskDispatchFailed::event()` preserves that reference-only fact when later dispatch/subscribers fail.
  Persistence remains committed. A matching retry does not redispatch; loss after commit/before dispatch is possible.
  No outbox, automatic replay, completeness, receipt or delivery guarantee is implied.
- Public Query results are immutable `StrictJson` objects containing ID, version, coherent state, created/updated
  milliseconds, TTL, polling advice and a state-bound public comparison revision. The producer version remains
  trusted application concurrency context, never MCP wire metadata. Definition, producer/scope context and comparison
  digests are omitted. Internal `Application\Mcp\Task\Projection\TaskProjection::fromTask()` owns these Query
  fields and visibility-bound HMAC revision encoding, using PHP internals without an Adapter dependency or new
  caller composition. It consumes the same immutable Task and one captured clock instant; `GetTaskHandler` keeps
  its final independent read/whole-state checks after projection. Domain owns `stateAt()` outstanding-input
  lifecycle state, readable metadata and protected `incarnation()`/`publicOrder()` evidence. Public-write changes
  compare Domain state values/metadata directly, not a serialized transport view. Public revisions include
  outstanding-form expiry without read-side writes and exclude
  private-only mutations; different visibility epochs/lifetimes are incomparable. See [the revision contract](mcp-task-live.md).
  Null unifies denied/unknown/cross-scope/expired/withheld state and does not identify an absence reason. Corrupt or
  failed storage raises fixed `TaskStorageFailed`, not null or empty success. Causes/stack traces remain private.

A Query performs no transaction, cleanup, event, receipt, retention renewal or cancellation. Reconnect checks current
read and disclosure permissions anew. Revocation cannot retract bytes previously disclosed. Consumer HTTP boundaries
must map exceptions safely; this slice adds no endpoint or generic exception-message projection.

## Finite bounds and retention

| Field | Accepted bound |
|---|---|
| ID | Exactly 32 lowercase ASCII hexadecimal bytes |
| Scope / producer | 1–96 ASCII reference bytes; first alphanumeric, remaining alphanumeric or `._:/-` |
| Original operation context | Object, at most 8,192 encoded bytes; StrictJson default depth 64 |
| Status message | Null or valid UTF-8, at most 1,024 raw bytes; empty string preserved |
| Outstanding input | Object, 1–16 requests; keys `[A-Za-z][A-Za-z0-9_-]{0,63}`; complete objects, never truncated |
| Complete state | At most 32,768 encoded bytes, depth 32, including message/input/result/error and JSON escaping |
| Complete stored row payload | At most 40,960 encoded bytes, independently checked before any replacement |
| Epoch milliseconds | Integer 0–253402300799999, with creation-plus-TTL within that range |
| TTL | Null unlimited, or integer 1–2147483647 milliseconds from original creation; immutable |
| Polling advice | Null absent, or integer 1–2147483647 milliseconds; explicitly replaced on each update |
| Private Task-local version / public-state counter | Separate integers 1–2147483647; no wrap/reset; only masked public revisions reach MCP |
| Error code / message | Signed 32-bit integer; nonempty UTF-8 message at most 1,024 bytes |

Zero/negative/overflow intervals reject; null does not invent a zero or default. TTL expiry occurs at
`now >= created_at + ttl`, independent of progress. It makes the row logically unavailable, not physically deleted,
completed or cancelled. Writes/retries to expired rows reject rather than renew retention. Consumers select retention
policy/schedules; this slice supplies no cron, automatic eligibility selection or expiration deletion. The explicit
removal Command is not an HTTP/MCP method and reads never perform cleanup. Encoded byte limits
include escaping and multibyte output; no final MCP envelope is constructed here. The protocol activation supplier
must bound its complete envelope, central metadata and text companions independently.

## Compatibility and public surface

TASK-00185 removes the never-tagged Application `TaskStore` and `DbalTaskStore` directly. Their replacements are
public Domain `TaskRepository` (implementable aggregate persistence), Application `CommittedTaskReader`
(implementable protected committed-read capability), and final constructible/callable DBAL `DbalTaskRepository`
and `DbalTaskTransaction`. `connection`, `requireOwnedTransaction` and `requireCommittedRead` are adapter composition
seams, not inward repository or transaction methods; consumers inject the existing inward contracts. The compatibility
manifest records the new declarations and operation shapes. No tagged name needs an alias/deprecation bridge.
TASK-00187 removes the never-tagged `TaskLifecycle`, `TaskInputLifecycle` and `TaskCancellationLifecycle` classes
without compatibility shims. Eight CommandHandlers and three QueryHandlers directly own admission, loading,
coordination and effects through operation-specific capabilities. Independent grants stay independent; aggregate
invariants stay with Task. ObservationRecorder and Notice orchestration are unchanged.
TASK-00188 removes provisional `Task::view()`/`revision()` directly: internal Application `TaskProjection` owns
bounded Query construction and native PHP HMAC framing/additive masking. No cryptographic provider variation or
external runtime dependency is introduced, so no encoding port, forwarding adapter or extra caller sequence is
needed. Domain retains atomic lifetime/public-write/expiry order, clock rollback and exhaustion checks; it does not
know visibility contexts or encoded revision tokens. The compatibility manifest classifies the new helper internal
and updates the aggregate operation shape; no tagged contract requires a bridge. Adapter namespace moves remain
a separate chore. Schema, retry/expiry/input/cancellation/removal/revision semantics,
GetTaskHandler construction and public Query/wire output stay unchanged.

TASK-00189 adds public final `Domain\Mcp\Task\TaskId` and directly types provisional Domain/repository/read/admission
and handle APIs. Definition, state, input batch and selection now inherit ValueObject with meaningful reconstruction,
equality/hash and deliberately structured JSON. `TaskInputs` remains nested lifecycle state, not a value. All local
release tags through v1.4.0 were inspected; none contains these Task APIs. No snapshot aliases, deprecations, schema
migrations, lifecycle or wire changes are introduced. The compatibility manifest classifies TaskId public with named
factory/callable contracts, not extensibility; internal TaskProjection remains internal. See the classification above.

TASK-00167 added five additive opt-in removal declarations classified public in `compatibility/manifest.json`:
Domain `TaskSelection`, `RemoveTasks`, `TaskDataRemoved`; Application `RemoveTasksHandler`, `TaskRemovalAdmission`.
They are final/immutable concrete classes or a consumer-implementable admission port. RemoveTasksHandler requires
removal admission, TaskRepository owns `remove(TaskSelection): ?TaskSelection`, and CommittedTaskDispatchFailed
carries either committed fact type. These provisional APIs change directly without compatibility
shims. No schema migration/version bump or dependency-floor/suppression change is needed: removal uses the existing
version-one row and namespace lock. Tagged contracts, including bus signatures and framework integrations, are unchanged.

Historically, the original 23 additive opt-in lifecycle declarations were classified public: seven Domain
values/messages (`Task`, `TaskDefinition`, `TaskState`, two Commands, Query, fact), fourteen Application declarations
(five ports, lifecycle, concrete Tool policy, three handlers, four failures), and two DBAL adapters. Classes are final;
ports are implementable; construction and callable contracts are explicit. Inventory totals also account for the
fourteen already-classified subscriber-inspection declarations omitted from the inherited totals (three Domain,
ten Application, one Adapter); this bookkeeping correction adds no promise for existing declarations.
Existing tagged CQRS, Tool, framework,
transaction, notice and Event Store APIs remain unchanged. No dependency-floor change or new suppression is required.

The default `fight_task_state` / `fight_tasks` namespace is version one and provisional until first tagged.
TASK-00199 adds public final readonly `DbalTaskTables`, optional `tables` construction for schema/transaction,
and adapter `DbalTaskTransaction::tables()` selection. Existing repository construction and all stored/wire shapes
remain unchanged. The selection is an Adapter configuration object, not a Domain value or an inward naming port.
Configured SQLite/MySQL/PostgreSQL evidence retains default variants and covers partial selections with populated
default counterparts, explicit installation/failure boundaries, lifecycle/terminal/input/confirmation/cancellation,
retention/removal, durable reopen and configured independent-process races and guarded HTTP polling.
TASK-00171 directly adds a private random lifetime incarnation/public-state counter to its coherent row representation,
required comparison-context admission, and read/live revision projection, without snapshot migrations or new tables.
Compatibility is against tags, not earlier development snapshots. There is no migration of old experimental formats,
release assignment or advertised/deployed client Tasks support. DBAL 4.4 and current APIs are preserved by using
schema-manager introspection, not additional internal `Schema` constructors.

Direct unit tests cover domain invariants, handlers, policy and DBAL storage/faults. Real three-database routed
journeys reconstruct across connections and independent PHP workers, hold delayed writers through commit, revoke
admission while waiting, race progress/input/cancellation outcomes and prove lost-ack/post-commit recovery. Removal evidence additionally covers the exact 100-ID boundary, current per-member grants, whole-row comparison/body
deletion, real three-database rollback/lost commit acknowledgment, post-commit failure and independent-process
create/update/remove/repeat/revocation races in both lock orders. These are library behavior evidence, not consumer
worker, deployed Agent OS/Pi, complete MCP extension or release certification.
