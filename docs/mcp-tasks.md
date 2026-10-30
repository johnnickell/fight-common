# Serving durable MCP Tasks

Explicit `Application\Mcp\Task\McpTasks` composition enables the complete
[`2026-07-28` Tasks extension](https://github.com/modelcontextprotocol/ext-tasks/blob/main/specification/2026-07-28/tasks.md)
on the existing guarded endpoint. It supplies start/get/update/cancel, not a worker or Workflow engine.
Polling needs no Subscriptions, Mercure, execution-inspection capability or original connection.
There is no tasks/list, tasks/result, live Task notification, automatic retry or job-control integration.

## Composition

First wire the actual [lifecycle](durable-tasks.md), [input](task-input.md) and
[cancellation](task-cancellation.md) handlers into the existing buses. They share the same Task store,
transaction, payload validation and independently supplied current permissions. In each trusted request context:

```php
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;

$tasks = new McpTasks($queries, $commands, $trustedScope, $trustedCaller);
$invoker = new McpToolInvoker($tools, $availability, tasks: $tasks);
$registry = new McpCapabilityRegistry($serverInfo, [
    new McpToolDiscovery($tools, $availability, $cursorKey),
    new McpToolInvocation($invoker),
    $tasks
]);
```

Supply this registry to the existing `Adapter\Mcp\Http\McpRequestHandler`, with the same Origin policy,
invocation guard, redaction-aware diagnostics, PSR factories and optional authentication middleware as ordinary
Tools. Consumers still own routing, authenticated caller resolution and business permission. Rebuild request-scoped
bindings when caller/scope changes. A scope, caller string, handle or capability declaration is not a grant.

The registry requires this exact `McpTasks` instance for all three methods and Tool invocation. It rejects partial,
contradictory or placeholder extension advertisement, including a bound Task invoker without its serving capability.
Actual bus routes remain a composition-root obligation: an absent/broken route fails safely at use, not a promise
that arbitrary consumer executors or dependencies can be health-checked during registration. `McpTasks` constructs
the real `McpTaskInput` and `McpTaskCancellation`, not substitute acknowledgment callbacks. Ordinary registries with
no Task composition remain usable. Discovery advertises exactly `extensions: {"io.modelcontextprotocol/tasks": {}}`;
there is no polling-only flag, task preference field or implicit enablement by another capability.

## Additive Tool entry

Opt in by implementing `Application\Mcp\Task\McpTaskTool extends McpTool`. Keep the ordinary `handle()` declaration
and its single `McpToolInfo` attribute (and optional Validation rules). Add:

```php
public function start(
    ApplicationData $input,
    McpProgressReporter $progress
): McpTaskHandle|McpToolOutput|McpInputRequired;
```

The invoker checks current availability, current Tasks support and complete Task composition **before** argument
validation or entering `start()`. It then uses handle's schema/rules and the existing metadata/failure boundaries.
A Task Tool may choose an ordinary `McpToolOutput` for this request; the normal output schema and response limits
still apply. An interactive Task Tool must also implement `McpInteractiveTool` and configure the ordinary interaction
protector to return `McpInputRequired` before Task creation. Its ordinary `resume()` return contract is unchanged;
it cannot return a Task handle. Durable input after creation uses the separate input lifecycle, not stateless resume.
Ordinary Tools are never converted into Task Tools implicitly.

Inside `start()`, generate a fresh `TaskDefinition::newId()`, capture the original Tool contract and arguments, and
execute `CreateTask` through CommandBus. Consumers own submission and its failure/reconciliation policy. Return
`new McpTaskHandle($id)` only for a durably created operation. Common then fetches `GetTask($trustedScope, $id)`
through QueryBus, including fresh read and whole-state disclosure checks, before acknowledging the handle.
An in-memory object or queued submission alone cannot pass this boundary. Creation grants do not imply disclosure;
failed/missing/withheld current state produces no successful handle acknowledgment even if creation committed.
Original contract validation uses retained context, not a new lookup of a possibly changed Tool definition.

Keep direct consumer calls to `handle()` ordinary: they cannot return a handle and must not start work inaccessible
to their caller. Direct `McpToolInvoker::invoke()` has no negotiation evidence and rejects Task Tools before entry.
For a non-declaring client needing this Task path, the error is `-32021` with
`data.requiredCapabilities.extensions["io.modelcontextprotocol/tasks"]: {}`. Every get/update/cancel request requires
its own declaration under `_meta["io.modelcontextprotocol/clientCapabilities"].extensions`; earlier support is not
cached. Malformed declarations follow existing safe metadata validation. Declaring support never forces the server
to choose asynchronous execution, and never grants permission. Only configured Tool invocation can emit Task results;
other request methods cannot use the Task discriminator.

## Wire data and finite budgets

Creation is a flat `resultType: "task"` plus `taskId`, `status`, ISO-8601 UTC millisecond `createdAt` and
`lastUpdatedAt`, `ttlMs` (duration from creation, or null), optional `pollIntervalMs` and optional `statusMessage`.
No private definition, comparison digest, internal version, cancellation intent or nested state is disclosed.
The message preserves an explicit empty string; absent optional fields are omitted rather than fabricated.

`tasks/get` accepts exactly `{ "taskId": "<32 lowercase hex characters>" }` and returns `resultType: "complete"`
plus that metadata and the full current detailed variant:

| Status | Additional fields |
| --- | --- |
| working / cancelled | None |
| input_required | All outstanding keyed `inputRequests`, each `method: "elicitation/create"` with `params.mode: "form"`, message and requestedSchema |
| completed | Original retained Tool `result`, including content, structuredContent and explicit isError |
| failed | Original safe mapped JSON-RPC `error`, not a Tool error result or arbitrary exception text |

A confirmation remains consumer-designated protected input; its public form is the standard elicitation request,
not a new wire request type. Private validation rules and accepted answers are never part of this projection.
A completed `isError: true` result stays **completed**. Failed stays **failed**; a quiet worker, acknowledgement,
expired handle, disconnected stream or successful Task get does not establish business success or execution.
The nested result is the original operation result, not another Tool invocation and not the outer get envelope.
The concrete payload policy supports the bounded text/form profile described in the supplier documents.

Default maximum **complete encoded JSON-RPC response** is 65,536 bytes, configurable with the fifth `McpTasks`
constructor argument from 1 through 65,536. This guard covers creation, get and mutation acknowledgments, after the
actual central server metadata, typed request ID, JSON escaping, nested structured/text companions and outer
envelope are encoded. Supplier state/context/form bounds remain independent. Application-valid data can exceed a
smaller serving budget or exceed the final budget after envelope/metadata overhead. It fails through the central
sanitized internal-error path; no truncation, hidden-form removal or successful empty replacement is used.

Missing, invalid, cross-scope, denied, expired, removed or withheld Task identities share safe `-32602`, with no
inferred reason for absence. Unexpected storage, route, projection and outer-budget failures use central `-32603`
and redaction-aware diagnostics. Private messages do not become stored errors. Get is read-only: no TTL renewal,
input consumption, removal, event or worker effect. Current Tool catalog availability does not grant or revoke
retained Task reads; the independent Task permissions/payload policy decide those on every request.

For get/update/cancel, `Mcp-Name` mirrors the exact `params.taskId` and `Mcp-Method` the method. Missing, duplicate,
mismatched and invalid fields follow the same safe header-mismatch rules as existing Tool mirrors, before admission
or mutation. Origin/POST/media negotiation, request byte/depth bounds and OAuth/authentication composition remain
unchanged. Protocol results are not JSend. Update/cancel use supplier-owned independent mutation grants, not generic
Task-read access. Their empty complete acknowledgments do not prove consumer continuation or actual stopping.

## Failure and reconnect recovery

| Boundary | Meaning and recovery |
| --- | --- |
| Before creation commit | No handle acknowledgment; confirmed rollback differs from uncertain storage outcome |
| Commit/ack uncertainty | Work may be retained; use fresh authorized shared reads and consumer-owned bounded reconciliation, never assume absence |
| Creation committed, submission failed | Durable state can remain working with no worker started; consumer reconciles/report actual failure, Common does not retry submission |
| Postcommit fact publication failed | State remains committed; retries cannot erase it or imply another publication attempt |
| Read/disclosure/response failed after creation | Handle acknowledgment may be lost; retained state/consumer job identity reconciliation remains independent |
| Original socket closes | No durable cancellation is requested; independent producer updates remain pollable after reconnect |
| Cancellation acknowledged | Only durable intent (or authorized terminal no-op), not stopping, rollback or a forced cancelled outcome |
| Input acknowledged/acquired | Retained admission/consumption only; consumer separately authorizes/executes and reports its actual outcome |

Same-content creation retries deduplicate only within the retained lifetime; updates use the existing Task-local
version fence. Never retry external effects merely because a response was lost. Terminal winners, input answers,
consumed confirmations and cancellation intent retain the supplier concurrency semantics. Consumers own clocks,
SQL/worker timeouts, admission limits, job identity, scheduling, external effects, retention/removal and quiescence.
No worker/outbox, exactly-once guarantee, compensation or atomic transaction with an external job system is supplied.

## Compatibility and public surface

Compatibility was classified against all released tags through `v1.4.0`, not development snapshots. The three
additions are `McpTaskTool` (implementable opt-in interface), `McpTaskHandle` (final constructible/callable identity)
and `McpTasks` (final constructible/callable explicit capability). Source namespaces remain `Application\Mcp\Task`.
`McpTool::handle()` and `McpInteractiveTool::resume()` signatures are unchanged; no implementer, subtype or caller
must accept a widened return union. Task Tools use a separate method deliberately, avoiding both a breaking caller
return change and an unauthorized release exception. Ordinary validation, errors, metadata, stateless forms,
destructive confirmation, progress and native adapters retain their released paths.

The optional trailing `McpToolInvoker` Task collaborator is additive; its accessors, matching invocation accessor,
`McpTasks::created()` and `McpResult::task()` are package-internal composition/result seams. No dependency floor,
public bus signature, schema, suppression or runtime boundary changes. Provisional supplier APIs stay in their
current Mcp homes without development compatibility aliases. Complete activation reserves the Tasks methods and
empty extension contract; previous supplier test-only partial routing is not production extension support.

## Package evidence and limits

`McpTasksTest` covers negotiation before effects (including direct invocation), complete composition, all state
shapes, independent permissions, escaped/multibyte exact/over final budgets, mirror/security rejection and safe
failure distinctions. Existing ordinary Tool/input/confirmation/progress/native suites remain the compatibility
proof, not removed or weakened tests. `McpTaskJourneyTest` uses real raw-socket HTTP and PHP cli-server, real routed
suppliers and DBAL SQLite/MySQL/PostgreSQL storage. Separate producer processes reconstruct state, issue forms,
observe cancellation, acquire confirmation and report actual completed/failed/cancelled outcomes. It exercises
closed connections, reconnect, response loss, partial/invalid input, stale producer rejection, revocation,
submission/publication faults and unavailable/storage-failed polling. PSR, native Symfony and native CodeIgniter
response emission are covered; existing framework suites qualify shared Laravel/Yii/Slim response contracts.
Supplier journeys separately retain real lock-order races, uncertain-commit/rollback and input/cancellation integrity
proof. This is package evidence, not a persistent HTTP deployment, starter receipt or qualification of Pi, Agent OS,
a proxy/server configuration, production worker or another MCP client. Nonvisual wire/state traces are useful proof;
screenshots of this library code would not demonstrate the contract.
