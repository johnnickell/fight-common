# Durable asynchronous Task state

This opt-in shared lifecycle persists and retrieves asynchronous operation state. It does **not** enable or
advertise MCP Tasks, change `McpTool::handle()`, run a worker, admit client input, request cancellation or remove
retained data. Those separately supplied capabilities consume this lifecycle. A Task is neither a planning TASK,
consumer job, Workflow nor execution authority. Polling has no Subscriptions, Mercure or Event Store dependency.

## Explicit composition

Use a dedicated idle DBAL connection to an owned SQLite, MySQL or PostgreSQL database. During exclusive deployment,
`(new DbalTaskSchema())->install($connection)` creates or validates the version-one namespace. The schema also offers
`addTo($schema, $platform)` for consumer migration composition; initialize `fight_task_state` with singleton/version
`1/1` when composing manually. MySQL uses InnoDB and `utf8mb4_bin`; partial DDL is an operator recovery condition.
There are no development-format migrations, automatic cleanup or schema changes to other Common capabilities.

Construct these explicit collaborators (names below are under `Fight\Common`):

- `Adapter\Task\Dbal\DbalTaskStore($connection)`, implementing Application `TaskStore` and
  `TransactionalUnitOfWork` on that **same** connection.
- Independent request/producer-scoped `TaskCreationAdmission`, `TaskUpdateAdmission`, `TaskReadAdmission`.
  Each decision uses trusted consumer context, never a submitted producer label as a credential. Mutation admission
  occurs after acquiring the namespace lock, before identity lookup, including retries. Read admission precedes lookup.
- `TaskPayloadPolicy`, which validates the retained **original** operation contract without executing it and supplies
  a fresh whole-state disclosure decision. Status/message, input, result and error each require permission and safe
  projection where consumer policy requires. Returning false withholds the entire view; never substitute an empty result.
- Existing `EventDispatcher` and a trusted `Closure(): int` server epoch-millisecond clock. For example,
  `static fn (): int => (int) floor(microtime(true) * 1000)`. Synchronize clocks; logical expiry depends on that clock.
- `Application\Task\Service\TaskLifecycle($store, $creation, $updates, $reads, $payloads, $events, $clock)`.

Register `CreateTaskHandler` and `RecordTaskStateHandler` with the existing Command router, and `GetTaskHandler`
with the Query router, all sharing that lifecycle. Handler namespaces are `Application\Task\CommandHandler` and
`Application\Task\QueryHandler`. Their Domain payloads are `Domain\Task\Command\{CreateTask,RecordTaskState}` and
`Domain\Task\Query\GetTask`. See `tests/TestCase/Task/TaskFixture.php` for executable explicit bus composition.
The existing bus signatures remain `CommandBus::execute(): void`, `QueryBus::fetch()` and `EventDispatcher::trigger()`.

A typical application sequence, after composing `$commands` and `$queries`, is:

```php
use Fight\Common\Domain\Task\Command\CreateTask;
use Fight\Common\Domain\Task\Command\RecordTaskState;
use Fight\Common\Domain\Task\Query\GetTask;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Task\TaskState;
use Fight\Common\Domain\Value\Basic\StrictJson;

$id = TaskDefinition::newId(); // Generate before the void Command; possession grants nothing.
$definition = TaskDefinition::create($id, 'project:example', 'producer:example', StrictJson::fromObject([
    'kind' => 'tool',
    'name' => 'example.operation',
    'revision' => 'retained-contract-v1',
    'output_schema' => (object) ['type' => 'object']
]));
$commands->execute(new CreateTask($definition, TaskState::create('working'), 60000, 500));
$view = $queries->fetch(new GetTask('project:example', $id));
// Only trusted producer evidence establishes this result. No operation is re-executed here.
$result = StrictJson::fromObject([
    'content' => [(object) ['type' => 'text', 'text' => 'Finished']],
    'structuredContent' => (object) ['ok' => true],
    'isError' => false
]);
$commands->execute(new RecordTaskState(
    'project:example', $id, 'producer:example', 1, TaskState::create('completed', result: $result)
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
It does not issue or consume confirmation, admit responses, allocate lifetime request keys or authorize continuation.
The input supplier must integrate those additional invariants with the same state/version/transaction, not a second
store. Production inputs and results must already be consumer-projected safe evidence where appropriate; JSON/schema
validation alone is not redaction. The concrete policy's required disclosure closure decides current safety/access
for the entire view, not just its final body. No default public-read policy exists.

## State, concurrency and retries

Creation starts `working`, revision 1. Each accepted producer update replaces state/payload/polling advice atomically,
advances the Task-local revision by one and preserves original definition, creation time and TTL. Both `working` and
`input_required` can move to any supported state; `completed`, `failed` and `cancelled` cannot be replaced. Timestamps
are server-owned, nondecreasing per Task, and not concurrency fences. Cancelled means reported producer evidence,
not client disconnect, silence, expiry or a cancellation request.

One version-row write lock serializes mutations across the namespace through **actual commit**, including concurrent
creates, repeats and future removal. This favors simple correctness over write throughput; consumers own bounded SQL
lock/statement timeouts. There is no automatic hidden retry. Current reads are one coherent committed row, no history
or snapshot across requests. Do not share the connection concurrently or mutate transaction state in policy callbacks.
Callbacks must be local bounded validation, never execute consumer jobs inside the transaction.

Identity is the exact case-sensitive `(scope, 32 lowercase hexadecimal application ID)` tuple; the definition binds
one producer and original operation context. Use `newId()` (128 random bits), not JSON-RPC IDs, job IDs or planning IDs.
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
no hidden tombstone or historical comparison archive remains. This slice does not supply removal. After deliberate
whole-identity removal, a fresh admitted creation could reuse a tuple; consumers must never rely on old retries beyond
retention/removal or deliberately reuse random IDs. The removal supplier must serialize deletion with this lock and
remove all comparison material atomically; it must define its own no-resurrection admission/lifetime policy truthfully.

## Durability and failures

`DbalTaskStore` rejects caller transactions, savepoint composition, closed boundaries and dirty-read isolation before
writes. It implements `TransactionalUnitOfWork` directly; an unrelated ORM unit of work does not enlist its writes.
Do not add an outer transactional Command filter. The store never commits or rolls back rejected caller work.
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
  milliseconds, TTL and polling advice. Definition, producer/scope context and comparison digests are omitted.
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
| Task-local revision | Integer 1–2147483647; no wrap/reset |
| Error code / message | Signed 32-bit integer; nonempty UTF-8 message at most 1,024 bytes |

Zero/negative/overflow intervals reject; null does not invent a zero or default. TTL expiry occurs at
`now >= created_at + ttl`, independent of progress. It makes the row logically unavailable, not physically deleted,
completed or cancelled. Writes/retries to expired rows reject rather than renew retention. Consumers select retention
policy/schedules; this slice supplies no cron, public removal or automatic expiration deletion. Encoded byte limits
include escaping and multibyte output; no final MCP envelope is constructed here. The protocol activation supplier
must bound its complete envelope, central metadata and text companions independently.

## Compatibility and public surface

23 additive opt-in lifecycle declarations are classified public in `compatibility/manifest.json`: seven Domain
values/messages (`Task`, `TaskDefinition`, `TaskState`, two Commands, Query, fact), fourteen Application declarations
(five ports, lifecycle, concrete Tool policy, three handlers, four failures), and two DBAL adapters. Classes are final;
ports are implementable; construction and callable contracts are explicit. Inventory totals also account for the
fourteen already-classified subscriber-inspection declarations omitted from the inherited totals (three Domain,
ten Application, one Adapter); this bookkeeping correction adds no promise for existing declarations.
Existing tagged CQRS, Tool, framework,
transaction, notice and Event Store APIs remain unchanged. No dependency-floor change or new suppression is required.

The new reserved `fight_task_state` / `fight_tasks` namespace is version one and provisional until first tagged.
Compatibility is against tags, not earlier development snapshots. There is no migration of old experimental formats,
release assignment or advertised/deployed client Tasks support. DBAL 4.4 and current APIs are preserved by using
schema-manager introspection, not additional internal `Schema` constructors.

Direct unit tests cover domain invariants, handlers, policy and DBAL storage/faults. Real three-database routed
journeys reconstruct across connections and independent PHP workers, hold delayed writers through commit, revoke
admission while waiting, race progress/input/cancellation outcomes and prove lost-ack/post-commit recovery. These
are library behavior evidence, not consumer worker, deployed Agent OS/Pi, complete MCP extension or release certification.
