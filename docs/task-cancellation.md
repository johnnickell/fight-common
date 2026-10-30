# Cooperative Task cancellation

This opt-in capability extends the [durable Task lifecycle](durable-tasks.md)'s existing row and actual-commit
transaction. Intent is not an outcome, worker observation, forced interruption, compensation or execution receipt.
Polling, input and cancellation need neither Subscriptions nor Mercure. No worker, outbox or stopping retry is added.

## Composition and independent authority

Construct `Application\Mcp\Task\Service\TaskCancellationLifecycle($store, $admission, $events, $clock)` with the same
`TaskStore`, `EventDispatcher` and trusted epoch-millisecond clock as the ordinary lifecycle. Register
`Application\Mcp\Task\CommandHandler\RequestTaskCancellationHandler` and
`Application\Mcp\Task\QueryHandler\GetTaskCancellationHandler` with the existing routers. The former handles Domain
`Command\RequestTaskCancellation`; the latter handles Domain `Query\GetTaskCancellation`, both under `Domain\Mcp\Task`.
No bus signature changes: Commands return void, Queries fetch immutable data and events describe committed facts.

Consumer `TaskCancellationAdmission` supplies two **independent, current** decisions:

- `allowsCancellation(scope, id, caller)` runs under the namespace lock before lookup, on initial requests,
  repeats and terminal no-ops. Task-read, producer and execution grants do not grant cancellation.
- `allowsCancellationObservation(scope, id, caller)` runs before every protected intent lookup. Cancellation
  authority alone never discloses intent, state, terminal payloads or original operation context.

Caller/scope are trusted consumer bindings, not credentials issued by Common. Identifiers, client capabilities,
submitted administrator labels and earlier answers grant nothing. False or throwing decisions conceal policy
text. Callbacks must be bounded/local and never control jobs or change transaction ownership.

```php
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskCancellation;

$commands->execute(new RequestTaskCancellation('project:example', $id, 'caller:example'));
$observation = $queries->fetch(new GetTaskCancellation('project:example', $id, 'worker:example'));
// null means denied/unavailable, not "no cancellation".
// A non-null observation has id, current version and nullable cancellation.
// cancellation contains only original requested_at milliseconds and admission version.
```

Observation is nondestructive and can reconstruct after process restart, missed events or failed callbacks within
retention. It emits nothing, renews nothing, and proves no worker saw or honored intent. Ordinary `GetTask` retains
its own read/disclosure checks and deliberately omits private intent. Actual terminal results require that separate
Query; neither cancellation acknowledgment nor the protected intent Query discloses result/error bodies.

## Durable admission, repeats and outcomes

The first admitted nonterminal request records exactly two nullable integer fields in the existing protected row:
`cancellation_requested_at` and `cancellation_version`. They become non-null together, containing a server-owned
nondecreasing timestamp and the new Task-local version. Admission increments version once, preserving state,
input requests/answers/consumption, original context, TTL and polling advice. It records no reason, private message,
caller ledger, stop attempt or cancelling protocol status. The two original values never change on retries.

The store owns actual commit, not an outer savepoint. Initial success additionally reconstructs through its shared
committed `read()` contract and verifies retained original intent before returning. Reconstruction failure,
disappearance or expiry before that verification withholds acknowledgment; it does not claim rollback. Concurrent
producer/input changes may advance version but must retain the same original intent. Reference-only `TaskChanged`
(scope/ID/admission version) is dispatched afterward, never a fabricated stopped-job fact.

Currently authorized repeats are harmless no-ops: no new version/fact, retention renewal, reset evidence or stop
obligation. Known completed/failed/cancelled Tasks also acknowledge as no-ops without adding intent or changing
terminal payloads. This is Common's selected package behavior, not a claim the specification mandates terminal
no-ops. Missing, denied, cross-scope, expired and removed Tasks safely reject, without resurrection or an inferred
job outcome. Whole-row removal deletes intent with the existing payload and comparison material; no hidden ledger
survives. TTL expiry is logical unavailability, never an implicit cancellation request.

Trusted producer transitions remain the only way to report actual completed/failed/cancelled outcomes. A stale
producer version conflicts rather than erasing intent or reopening a terminal winner. Consumers can explicitly
reread and reauthorize before reporting their actual outcome at the new version. Work can complete or fail despite
acknowledged intent; stopping is not guaranteed or automatically retried.

## Input and consumer continuation

The real [input lifecycle](task-input.md) shares the cancellation lock/version. Submission can still accept answers
after intent: cooperative intent is neither form refusal nor terminal state. Issuance, retirement, answer admission,
acquisition and producer updates all preserve original intent. Accepted answers cannot be replaced; consumed
confirmation cannot be restored by later cancellation, failed delivery or uncertain consumer effects.

`TaskInputLifecycle` now requires `TaskCancellationAdmission` as its seventh constructor argument. Acquisition checks
both current business continuation permission and current intent-observation permission before loading/mutating.
`GetTaskInput` requires its existing input-observation grant **and** current cancellation-observation grant; otherwise
it returns null, not a false absent-intent value. Its coherent protected view adds current `version` and nullable
`cancellation` beside definition/terminal/groups. These provisional APIs change directly, without snapshot aliases.

After successful acquisition, consumers fetch this current protected view before deciding whether/how to continue.
A pre-acquisition observation is not current continuation evidence. If disclosure/authority is revoked or the view
is unavailable, do not infer permission or absence of intent. Acquisition remains single-use even then. Consumers
may honor intent or deliberately continue under their own current execution authority. The read and external effect
are separate: cancellation can arrive after the read; Common promises no atomicity with a network/job effect.

Both admission orders preserve evidence. Cancellation-first makes a stale issuance/producer version conflict, while
fresh input admission cannot hide intent from continuation. Input/acquisition-first preserves accepted answers and
consumption when cancellation follows. Actual terminal-first outcomes cannot be reopened; input-first causes stale
terminal updates to conflict until a fresh producer decision. No automatic refusal, compensation or rollback follows.

## Failure and recovery

- Unsupported enclosing transactions, savepoints, dirty reads and closed stores reject before mutation, leaving
  unrelated caller work untouched. No network/job-control call spans the admission transaction.
- Known rollback preserves the prior row. Begin/commit/rollback uncertainty closes the existing boundary and raises
  `TaskStorageFailed`; `outcomeUncertain()` requires fresh authorized reconciliation, never an invented stop receipt.
- Lost acknowledgment may follow actual commit. Reconnect with current observation permission and explicitly retry
  only the intent Command under current cancellation authority; repeats create no new stopping obligation.
- Failed committed reconstruction withholds success. Postcommit event/subscriber failure raises
  `CommittedTaskDispatchFailed` without undoing intent. No callback/event is required to recover intent by Query;
  missing/failed delivery does not promise replay. Reference-only facts contain no payloads/answers/diagnostics.
- A consumer stopping attempt can fail or have uncertain external effects after admission/observation. Preserve those
  distinctions and report only actual producer evidence. No exactly-once worker control, automatic effect retry,
  external-effect rollback, compensation or eventual cancellation is supplied.

## Actual tasks/cancel method

`Application\Mcp\Task\McpTaskCancellation($commands, $trustedScope, $trustedCaller)->handle($request)` implements
only `tasks/cancel`. It checks that request's object declaration
`extensions["io.modelcontextprotocol/tasks"]` **before mutation**, then accepts exactly one `taskId` parameter.
The ID must be exactly 32 lowercase ASCII hexadecimal bytes; scope/caller use the existing 1–96-byte ASCII reference
grammar. No unchecked reason, message, arbitrary status/result or private diagnostic field is accepted. Form
elicitation capability is not required just to cancel.

Wrong method is `-32601`; missing Tasks capability is `-32021` with
`data.requiredCapabilities.extensions["io.modelcontextprotocol/tasks"] = {}`. Invalid/nonexistent/denied/expired IDs
and admission conflicts map to fixed safe `-32602`. Storage, uncertain commit, reconstruction and postcommit failures
propagate to the existing central sanitized `-32603` diagnostic path, never a successful empty result or private text.

Success is exactly `{"resultType":"complete"}`. It is neither current Task data nor proof of stopping. The semantic
result budget is 128 encoded bytes, including subsequently added metadata; overflow rejects rather than truncating.
TASK-00168 owns complete activation/advertisement, actual outer-envelope bounds, guarded HTTP composition,
`Mcp-Name`/taskId and `Mcp-Method` mirrors and combined Tool-start/cancel/outcome-get journeys. This helper alone
implements no capability and advertises no partial extension. Request-scoped `McpToolExecution::cancel()`, stream
closure and progress cancellation remain unchanged. `notifications/cancelled` is never used for durable Tasks.

Sources verified for this implementation: official [Task Cancellation and Error Handling](https://github.com/modelcontextprotocol/ext-tasks/blob/main/specification/2026-07-28/tasks.md)
and [CancelTaskRequest / CancelTaskResult](https://github.com/modelcontextprotocol/ext-tasks/blob/main/schema/2026-07-28/schema.ts).
The contract is **2026-07-28**, not older experimental APIs. Its empty acknowledgment and cooperative semantics do
not guarantee a cancelled outcome. Package evidence is not deployed Pi/client, worker or HTTP-server qualification.

## Bounds and retention

The original Task version/timestamp/TTL/40,960-byte complete-row limits remain binding. Intent uses only two bounded
integers, no unbounded journal. Both-null means absent; reconstruction rejects mismatched nullability, requested time
outside creation–updated time, and original admission version outside 2–current version. Exhausted version rejects
new intent, while valid existing-intent/terminal repeats remain no-ops. Reads expose only fixed keys and finite
identity/version/time values, with no arbitrary output upload. JSON metadata/escaping/multibyte cases are included
in the method's encoded semantic result bound. Full outer serving proof belongs to activation.

## Compatibility and public surface

TASK-00170 adds seven additive opt-in cancellation declarations in `compatibility/manifest.json`: Domain Command
and Query; Application admission port, lifecycle, two handlers and method implementation. Concrete classes are final
and readonly; the admission interface is consumer-implementable. Public construction/callability are explicit.

Inspection of actual tags through `v1.4.0` found no `Mcp/Task` declarations or Task tables. The existing Task APIs,
input constructor/view and current version-one JSON schema are never-released and provisional. They change directly
with two new private fields, without old-development codecs, aliases, migration or schema-version bump. Existing
released CQRS, transactions, ordinary forms/confirmation, request-scoped cancellation and framework contracts remain
unchanged. No dependency floor, suppression, packaging/release test or partial extension advertisement is added.

Direct tests protect domain fences, independent grants, safe bounded method behavior, reconstruction and failure
paths. Real SQLite/MySQL/PostgreSQL journeys use routed Commands/Queries, fresh connections, independent PHP
observers/contenders, actual input/confirmation, both lock orders, revocation while blocked, real constraints and
commit/response/postcommit faults. Consumer fixtures distinguish admission, observation, stopping attempt and actual
outcome. The final complete product gate and exact coverage receipt are recorded in TASK-00170's ignored handoff;
independent Review and QA remain separate obligations.
