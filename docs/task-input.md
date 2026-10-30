# Durable Task input

This opt-in input path extends [durable Tasks](durable-tasks.md)'s **same row, connection, version fence and actual
commit lock**. It retains supported forms and consumer-designated confirmations, admits partial response maps and
supports protected answer observation and single-use continuation admission. It is not a worker, second interaction
database, public status setter, business approval policy or exactly-once external-effect mechanism.

`Application\Mcp\Task\McpTaskInput` implements the actual `tasks/update` method. It intentionally does **not** implement
a capability or advertise/register an incomplete Tasks extension. TASK-00168 owns complete extension activation,
per-request serving capabilities, `Mcp-Name`/`taskId` HTTP mirrors, complete envelopes and combined Tool-start HTTP
journeys. TASK-00170 owns actual cancellation-intent races; trusted producer terminal outcomes are covered here.
The protocol reference is the official [2026-07-28 Tasks contract](https://github.com/modelcontextprotocol/ext-tasks/blob/main/specification/2026-07-28/tasks.md).

## Composition and authority

Create `Application\Mcp\Task\Service\TaskInputLifecycle($store, $producers, $admission, $payloads, $events, $clock)` using
the **same** `TaskStore`, producer `TaskUpdateAdmission`, payload policy, event dispatcher and server-millisecond
clock as the ordinary lifecycle. `TaskInputAdmission` supplies three independent current decisions:

- `allowsSubmission(scope, id, caller)` authorizes input admission, including empty/repeated submissions.
- `allowsObservation(scope, id, caller)` authorizes protected consumer observation, not ordinary Task disclosure.
- `allowsContinuation(scope, id, caller, group)` authorizes the current business continuation, not just reading it.

These use trusted request/worker context. A caller string, Task ID, prior permission, capability declaration, read
permission or administrator label is not a grant. Consumers own the policy and must authorize the exact original
operation and its arguments at continuation, not infer permission from an accepted answer. Decisions must be local,
bounded and must not change connection ownership or execute consumer work inside the transaction.

Register `Application\Mcp\Task\CommandHandler\{RequestTaskInputHandler,SupplyTaskInputHandler,AcquireTaskInputHandler,RetireTaskInputHandler}`
and `Application\Mcp\Task\QueryHandler\GetTaskInputHandler` with the existing routers. Each takes the input lifecycle.
Commands use `CommandBus::execute()`, Queries `QueryBus::fetch()` and reference-only facts `EventDispatcher::trigger()`.
No auto-discovery, outer transaction filter or consumer scheduler is installed. `tests/TestCase/Mcp/Task/TaskFixture.php`
shows complete explicit registration; it is a test consumer, not production authorization policy.

Producer issuance/retirement recheck `TaskUpdateAdmission` under the namespace lock **before lookup**, and check the
stored producer independently. Submission/acquisition check their own grants under that same lock before lookup or
protected form validation. Observation checks its own grant before lookup and never consumes an answer. False or
throwing policies never expose private diagnostics. Ordinary `GetTask` still requires independent `TaskReadAdmission`
and whole-view `TaskPayloadPolicy::mayDisclose()`. That closure must permit **all** returned public forms, not merely
the status; a shared handle does not grant another caller's forms. Protocol serving must additionally require current
form-elicitation support. Task negotiation alone never enables arbitrary server-to-client interactions.

## Issue and retrieve supported requests

```php
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequest;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Task\Service\TaskInputForms;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskInput;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;

$form = McpInputRequest::form('Choose a public label', [
    'type' => 'object',
    'properties' => ['label' => ['type' => 'string']],
    'required' => ['label']
], [['field' => 'label', 'label' => 'Label', 'rules' => 'not_blank']]);
$input = McpInputRequired::fromRequests(['label' => $form]);
// Use McpInputRequired::confirmation(...) only when consumer policy requires all-accept confirmation.
$batch = TaskInputForms::batch($input, 'caller:example', $serverNowMs + 60000);
$commands->execute(new RequestTaskInput('project:example', $id, 'producer:example', $observedVersion, $batch));
$view = $queries->fetch(new GetTask('project:example', $id));
```

The immutable original `TaskDefinition` must retain operation identity/revision, original arguments and other bounded
context needed by consumer policy. A batch binds caller, absolute expiry, ordinary/confirmation mode, original form
names, complete schema/runtime rules and the exact public description. Its Task row supplies scope/Task/original
operation binding; confirmation additionally requires an explicitly retained original `arguments` object, including
an empty object for an argument-free operation; the first allocated key identifies the confirmation group. No mutable catalog or client form
can replace that authority. The factory snapshots plain `StrictJson`, not PHP object serialization or executable
client rules. Issuance revalidates the retained forms and their exact descriptions before persistence.

The Task allocates `input-<namespace>-1`, `input-<namespace>-2`, …, never caller-selected response keys.
The namespace is the first 128 bits of SHA-256 over exact scope, a NUL separator and Task ID; it is technical binding,
not a secret or permission. Different Task/scope keys are inactive rather than transplantable to a matching form. They are unique for the **whole
retained Task lifetime**. `GetTask` returns every outstanding permitted form in `state.input`, using the existing
internal `{type: form|confirmation, message, schema}` representation. Private rules, bound caller, answers and
comparison digests never enter this public view. Complete protocol activation maps those descriptors to the standard
`inputRequests` elicitation requests; a confirmation remains supported form elicitation, not a new arbitrary RPC.

Keys remain stable across polling and partial responses. Issuance is version-fenced and does not silently replace
an existing group. `RetireTaskInput(scope, id, producer, expectedVersion, group)` explicitly removes a superseded,
expired, refused or consumed group's contracts/answers. Its counter remains: old keys cannot identify new requests.
Retirement does not cancel the Task or remove unrelated groups. Retire before issuing more input when retained
capacity is full. Exhaustion rejects atomically; counters never wrap, reset or forget old keys. Row removal ends that
lifetime, with the separately documented deliberate-recreation rules; use fresh random Task IDs for new operations.

`RecordTaskState` cannot create/replace outstanding contracts or clear unanswered input by reporting `working`.
Nonterminal producer state must preserve the exact outstanding map; terminal producer evidence can still win under
the existing expected-version fence. The input lifecycle alone updates requests/answers and coherent status.

## Partial response admission

`SupplyTaskInput(scope, id, caller, StrictJson $responses)` carries a JSON object keyed by request identity. Each
**active** value has `action: accept|decline|cancel`; accept requires object `content`, while decline/cancel must omit
content. `TaskInputForms` selects only currently active keys and deliberately applies the unchanged exact-key
`McpInputResponses::validate()` to that subset. It validates **all** selected envelopes and accept-only schema/runtime
rules before constructing the batch. Any invalid active entry rejects the entire batch without consuming valid peers.
Runtime-rule/exception text is private; the failure is fixed `TaskRejected`, without a private exception chain.

Unknown, answered, superseded, expired and other-caller keys are inactive. Their content is not interpreted, restored,
validated or applied, even when mixed with active responses. Earlier answers cannot be replaced. Overall object,
key, count, encoded-byte and depth checks still apply to **every** entry: ignored input cannot evade work bounds.
Partial acceptance leaves remaining forms outstanding and status `input_required`. When none remain, status is
`working`, not completed or executed. Empty/all-ignored maps are admitted no-ops with no version change or fact.
Known, currently authorized retained terminal Tasks also acknowledge as no-ops, without validating inactive content.
Denied/missing/cross-scope/expired/removed Tasks share safe rejection, never resurrection or an invented absence reason.

All active answers and outstanding/status changes commit atomically before acknowledgment. Independent contenders
serialize: the winner's answer remains, and later identical/conflicting submissions ignore that satisfied key without
another admission/fact. Terminal-first input is ignored; input-first makes a stale producer update conflict. A producer
may explicitly reread/re-authorize and report its actual outcome at the new version; there is no hidden retry.

## Confirmation and protected continuation

Confirmation group members may arrive separately. Partial affirmative input never authorizes continuation. Every
required member must accept; **any active decline/cancel** retires the group's outstanding requests and makes
acquisition impossible, including against racing acceptances. Unrelated groups remain intact. Later responses to a
retired/already-answered key are ignored, not a way to reverse refusal or replace a committed affirmative answer.
Refusal is typed input, not whole-Task cancellation. Ordinary decline/cancel remains available to consumer meaning
and does not retire unrelated ordinary forms.

```php
use Fight\Common\Domain\Mcp\Task\Command\AcquireTaskInput;
use Fight\Common\Domain\Mcp\Task\Query\GetTaskInput;

$observed = $queries->fetch(new GetTaskInput('project:example', $id, 'caller:example'));
// Inspect the exact group and its answers under current consumer authorization.
// If ready, issue acquisition before any consumer continuation:
$commands->execute(new AcquireTaskInput('project:example', $id, 'caller:example', $group));
// A successful return admits this invocation once. Consumer work happens separately.
```

`GetTaskInput` returns null for denied/missing/expired state; an authorized existing Task with no matching groups has
an empty map. It returns the immutable original definition and `terminal` flag plus matching `groups`, keyed by the
first request ID. Each group exposes durable typed `answers`, generated-key-to-original-form `names`, `confirmation`,
`expires_at`, `expired`, `refused`, `consumed` and current `ready`. It returns no runtime rules and performs no mutation,
event, cleanup or acquisition. It is a protected consumer Query, never a general notice feed or public tasks/get view.
Complete original context depth remains supported. Reads and admissions do not renew Task TTL or request expiry.

Acquisition freshly checks separate business authority, exact caller/group binding, unexpired complete answers,
non-refusal, nonterminal Task state and unconsumed status under the **same actual transaction**. It durably marks the
group consumed before returning; a second acquisition conflicts. Answers remain observable for reconciliation until
explicit retirement/Task removal/retention. Consumer work is not invoked by Common. A successful Query, input update
acknowledgment or observed `consumed` flag is **not** an execution receipt or authority for another worker to redispatch.

- Before durable commit, failure leaves the previous state after known rollback. Explicit retry still reauthorizes.
- Uncertain begin/commit/rollback closes the store with `TaskStorageFailed::outcomeUncertain()`. Use a fresh connection
  and protected Query. In particular, consumed confirmation cannot be restored after lost acquisition acknowledgment.
  Do not blindly execute or retry external work because the response was lost.
- After durable admission/acquisition, failure/disconnect/consumer exception cannot undo answers or consumed status.
  Consumers own scheduling, business meaning, current authority and reconciliation of uncertain effects.
- Reference-only `TaskChanged` is dispatched after actual commit. `CommittedTaskDispatchFailed` preserves committed
  state; retrying answered keys emits nothing. There is no outbox, guaranteed delivery or automatic retry engine.
- Caller transactions/savepoints/dirty-read isolation reject before admission/writes, without touching caller work.
  No connection is shared concurrently, and no external effect is claimed atomic with the Task transaction.

Expired active forms disappear from fresh public projection without a read-side write/fact; version is a mutation
fence, not an expiry lease. While any requests remain outstanding, polling preserves the admitted producer status
message exactly, including empty string versus null, even when some requests expire. Once all requests expire, the
existing projection is `working` with no message or input; stored producer state is unchanged. Expired answers never acquire. Consumers can explicitly retire and issue a fresh group,
with new keys/current authority, rather than revive a confirmation. Task TTL may make the entire row unavailable first.

## Actual tasks/update implementation

Compose `McpTaskInput($commandBus, $trustedScope, $trustedCaller)` in the later complete Tasks capability's dispatcher.
Its `handle(McpRequest)` accepts only `tasks/update`, checks **that request's**
`extensions["io.modelcontextprotocol/tasks"]` and `elicitation.form` object declarations before dispatch, and accepts
exactly `taskId` and `inputResponses` outer parameters. It constructs the shared `SupplyTaskInput` Command, never SQL,
producer status, Tool execution or a result upload. Missing capabilities produce safe `-32021` required-capability
errors; wrong method is `-32601`; invalid parameters, concealed/unavailable state or admission conflict are safe
`-32602`. Storage/uncertain-commit/postcommit failures propagate to the existing central safe diagnostic path, not
a false success or raw public diagnostic.

Success is exactly `{"resultType":"complete"}`, with no business result or execution claim. The semantic result has
a 128-byte budget retained when metadata is added. Full extension activation owns complete outer-envelope budgeting,
central metadata and guarded HTTP behavior. This method helper alone advertises nothing. Package evidence is not
actual Pi/client, consumer worker, hosted deployment or release qualification.

## Finite bounds

All limits are inclusive; overflow rejects atomically, never truncates, evicts answers silently or reuses keys.

| Material | Bound |
|---|---|
| Neutral caller | Same 1–96-byte ASCII reference grammar as Task scope/producer |
| Original form name / supplied response key | `[A-Za-z][A-Za-z0-9_-]{0,63}` |
| One batch / all retained requests / retained groups | 1–16 / at most 16 / at most 16 |
| Lifetime issued keys | 1,024; fixed scoped-Task namespace and monotonically increasing counter survive payload retirement |
| One private batch | 24,576 encoded bytes, depth 40; public descriptions and runtime rules included |
| Complete retained input bookkeeping/contracts/answers | 32,768 encoded bytes, depth 40 |
| One response map, including ignored entries | At most 64 keys, 32,768 encoded bytes, depth 32; empty object allowed |
| Public form message | At most 1,024 UTF-8 raw bytes; nonblank and supported existing flat form schema |
| Original operation / full state / complete stored row | Existing 8,192 / 32,768 / 40,960 encoded-byte bounds remain independently binding |
| Absolute request expiry | Valid Task epoch milliseconds, strictly later than issuance; acquisition strictly before expiry |
| Version / Task TTL / polling | Existing lifecycle bounds; input changes increment the same version and preserve TTL/polling |

The whole-row bound can reject a combination that fits individual sub-budgets. JSON escaping and multibyte data count
at each encoded boundary. Retained answers count until explicit retirement; there is no unbounded payload archive.
Total CPU/allocation is finite under these envelopes, but consumers still own upstream parsing limits, DB timeouts,
authorization costs, storage retention and consumer scheduling.

## Compatibility and public surface

TASK-00169 adds sixteen additive opt-in input declarations classified public in `compatibility/manifest.json`:
Domain `TaskInputBatch`, `TaskInputs`, four Commands and one Query; Application admission port, form adaptation,
input lifecycle, four Command handlers, Query handler and MCP method implementation. Final concrete classes are
not extensible; `TaskInputAdmission` is consumer-implementable. Factories/constructors/callability are classified
independently; `TaskInputForms` promises static operations, not utility construction.

The unreleased Task representation directly gains private input bookkeeping in its current version-one JSON payload;
no new table, schema migration/version bump, hidden payload copy or prior-development codec is added. Producer input
issuance now uses the retained-contract Command instead of raw `RecordTaskState` replacement; current Task views may
filter expired forms using server time. These provisional APIs/schema change directly against the tagged-release
compatibility rule, not earlier snapshots. Existing tagged CQRS/framework/transaction APIs, ordinary stateless exact-key
and deliberately replayable token behavior, and `McpConfirmationStore` remain unchanged. No dependency-floor change,
new PHPStan exception, gate exclusion or advertised extension is introduced. DBAL 4.4/current remain supported.

Direct unit evidence covers the owning production statements, bounds, protected errors, admission/observation and
method semantics. Real SQLite/MySQL/PostgreSQL Command/Query journeys reconstruct across connections and use real-driver
commit fault injection and independent PHP processes for duplicate/conflicting submission, producer terminal winners,
refusal/acceptance, acquisition and revocation while lock-blocked. Real constraints prove rollback; actual committed
writes with lost acknowledgment prove uncertainty separately from postcommit dispatch failure. Existing removal and
ordinary interaction suites remain compatibility evidence. This does not claim exactly-once business execution.
