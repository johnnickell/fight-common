# Live Task state and reconnect ordering

Optional `Application\Mcp\Subscription\McpSubscriptions` integrates full Task state on the existing guarded
POST `subscriptions/listen` stream. Ordinary [Task start/get/input/cancel](mcp-tasks.md) remains independent.
The [official Tasks 2026-07-28 schema](https://github.com/modelcontextprotocol/ext-tasks/blob/main/schema/2026-07-28/schema.ts)
uses **`notifications.taskIds`**, despite its stale `tasksStatus` comment. Its extension declaration is the empty
`io.modelcontextprotocol/tasks` object: there is no Task-notification capability setting. Core
[Subscriptions](https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/subscriptions) owns acknowledgment,
correlation and completion. This is package support, not qualification of deployed Pi, Agent OS or another client/proxy.

## Composition and authority

Compose the complete Tasks capability and Tool invoker as in [MCP Tasks](mcp-tasks.md). Pass that **same** `$tasks`
instance as the optional `tasks:` collaborator of `McpSubscriptions`, together with the required consumer-implemented
`taskDisclosure:` guard, then pass the subscriptions to the registry's third argument. For Task-only composition:

```php
$subscriptions = new McpSubscriptions(
    $queries,
    $tracking,
    $subscriptionAdmission,
    $trustedTrackingSelection,
    tools: false,
    resources: false,
    maxFrameBytes: 65536,
    tasks: $tasks,
    taskDisclosure: $taskDisclosureGuard
);
$registry = new McpCapabilityRegistry($serverInfo, $capabilities, $subscriptions);
```

`$tracking` is the existing idle dedicated `DbalSubscriptionStore`; `$trustedTrackingSelection` is a trusted
`NoticeSelection` covering **all** opening context, including Task scope and requested/accepted IDs. These names
come from `Application\Mcp\Subscription`, `Application\Mcp` and `Domain\Mcp\Notice`; the DBAL store is in
`Adapter\Mcp\Subscription`. The selection is technical access context, not Task-read authority. Consumer composition
must not label Task metadata with a narrower/unrelated notice scope. Independent subscriber inspection grants must
permit that complete context; it never becomes proof of delivery or continuing authorization.

The `$tasks` object owns shared `GetTask(scope, id)` Queries and the same complete projection as ordinary reads.
`GetTaskHandler` obtains committed aggregates through `CommittedTaskReader`, separately from the Domain
`TaskRepository` and injected transaction boundary used by mutations. The [DBAL composition](durable-tasks.md#explicit-composition)
shares the exact dedicated connection and preserves idle committed-read checks; no presenter SQL, local event listener,
general retained feed, Mercure publisher or captured Task history supplies state. Task-only and empty accepted streams
do **not** call `RecoverNotices`; notice storage/global evidence permission
is not a prerequisite. Enable Tools/Resources/Prompts separately for mixed streams: only accepted requested generic
families engage their existing qualified notice source. A mixed stream then inherits that source's continuity rules.
No Prompt or operator-inspection capability is required. Registry composition rejects missing/mismatched Tasks.

`McpSubscriptionAdmission::allows(subscriber, 'taskIds', taskId, null)` independently grants the **whole** technical
signal/ID at opening and each disclosure. The existing `$uri` argument carries the exact Task ID for this filter;
there is no Resource read. Fresh `TaskReadAdmission::allowsRead()` and `TaskPayloadPolicy::mayDisclose()` additionally
protect complete state and individual input/result/error/status fields through the shared Query. General notices,
Tool/catalog permission, recipients, a previous get, an acknowledgment or a privilege label grant none of these.
All callbacks must be bounded and use current trusted request context. Their Boolean results alone cannot authorize
later bytes. Task live composition requires the neutral `McpTaskDisclosureGuard` contract below; there is no allow-all
default. Common defines no identity, user, role or Permission policy and imports no AccessControl types.

`TaskReadAdmission::comparisonScope(scope, id)` is a required **trusted visibility context**, not a permission:
return a stable bounded reference (the ordinary 1–96 ASCII reference contract) binding caller, scope and visibility
policy epoch. It must agree across processes/get/listen/reconnect while the same complete view remains eligible.
Rotate it after any visibility discontinuity, including revocation/regrant or a change in eligible fields/context;
never reuse an old epoch. Consumers own policy-epoch durability and detecting those changes. If that binding is
unknown, fail comparison/read safely instead of inventing a stable scope or sharing another caller's context.
The returned reference itself is never disclosed. Get checks current read and whole-state disclosure again after
source, validation, projection, clock and comparison-context work; opening never freezes authority.

## Guarded disclosure boundary

`McpTaskDisclosureGuard::run(string $subscriber, array $states, Closure $disclose): void` receives trusted subscriber
context and an exact Task-ID-to-complete-DetailedTask map. This map is proposed protected data, **not authority**.
At opening it contains all finally accepted Task states, so no earlier member's grant can be invalidated by a later
member's sourcing. For each baseline/update it contains one state. Preserve separate current signal, Task-read,
whole-state and individual status/input/result/error/revision eligibility; never infer one from another.

The consumer must synchronize current original identity/credential, applicable Permissions and application-owned
Task relationships/scope with **every relevant writer** through the supplied synchronous bounded action. The guard
must freshly approve each exact proposed state, including its visibility epoch and protected fields, or throw before
calling the action. Do not authorize a previously eligible snapshot under a newly incompatible scope. Reads must be
authoritative, not a cached principal/Boolean/check result. Coordinate concurrent **and reentrant** authority writes:
writers winning before acquisition affect admission; conflicting writes during it wait/reject until release; writers
after release affect the next disclosure. An unrelated reader lock is insufficient. An unsupported authority/scope
participant makes that composition unsupported, not allowed. Common cannot verify that a trusted port tells the truth.

Common prepares the coherent shared Query/projection before acquiring this guard. It then invokes the final
owner-bound tracking heartbeat **inside** the admitted action, checks returned freshness/cancellation/deadline and
encodes/measures the complete frame before handing bytes off. Thus owner close/cleanup during sourcing or guard
admission prevents new output. Tracking never grants permission. A tracking implementation must not mutate protected
authority outside the consumer's qualified synchronization. Tracking remains a short dedicated transaction; no
tracking/Task transaction or authorization fence spans stream lifetime, idle waits or a scheduling pause. A concurrent
technical close *after* that final serialized readiness check may race the already admitted write; tracking is an
observation, not atomic network delivery. Every subsequent disclosure checks again.

The existing Fiber pauses **before** this guarded operation, with no protected bytes or allow cached across that pause.
The actual native `McpResponseEmitter` calls the operation with its synchronous **echo + flush** sink; neither queuing a
frame nor `Fiber::suspend()` is the protected action. The guard remains held until that bounded write/flush returns or
throws, then releases before the producer resumes or waits. Symfony/Laravel/CodeIgniter/Slim/Yii use that same emitter.
Consumers must qualify finite SQL/lock/SAPI write timeouts and buffering/backpressure: PHP cannot preempt blocked I/O.
This is admission to the SAPI byte handoff, **not** atomic TCP delivery, client receipt, processing or retraction.

Explicit PSR `read(n)` instead admits the byte handoff into that caller's buffer, not its future network write.
Each fragment re-sources and re-guards; only an offset/SHA-256 frame identity survives. A changed projection stops
rather than splicing versions. A lifetime/work-budget interruption after a fragment fails without appending a
successful completion to that incomplete frame. Revocation can leave an already delivered partial frame, never
retract its prefix.
`getContents()`/string conversion is explicit consumer buffering, not live authorization for a later write. Do not
replace the native emitter with a generic read-then-send emitter and claim the same protected network boundary.

Invoke the action exactly once synchronously on its calling Fiber, never save, retry, defer, yield or merely enqueue it.
Common rejects omitted/repeated/late/foreign-Fiber actions; a broken guard may already have admitted the first write
before a subsequent fault. Throwables go to the existing private diagnostic/closure owner; exceptions cannot prove no
bytes were handed off or undo an effect. No silent retries, permission lease or graceful success after a fault.

This seam is compatible with composing a consumer's synchronized bounded-action guard (for example AccessControl's
`AgentContinuationGuard::run`) around `$disclose`, with exact trusted requirement/scope association and complete
snapshot eligibility in that guard's scope checks. AccessControl owns credential/Permission checking; the application
owns Task relationships, field policy, writer synchronization and wiring. This is an integration contract, not shipped
AccessControl adoption, a Common authorization engine or a mandatory production consumer deployment gate. Controlled
consumer fixtures prove package conformance; real locks/authorities/proxies require separate consumer qualification.

## Wire and finite filters

Declare Tasks on **each** request under `_meta["io.modelcontextprotocol/clientCapabilities"].extensions`:

```json
{"io.modelcontextprotocol/tasks":{}}
```

Request `params.notifications: {"taskIds":["0123456789abcdef0123456789abcdef"]}`. Up to **16** submitted entries,
counting duplicates, are accepted structurally; each is exactly 32 lowercase hexadecimal ASCII characters. Non-list,
non-string, malformed or oversized input rejects with safe `-32602` before opening. Duplicates collapse in submitted
order. Empty arrays request nothing. Missing Tasks declaration on any `taskIds` request, even empty/unsupported,
returns **-32021** and `data.requiredCapabilities.extensions["io.modelcontextprotocol/tasks"]: {}` before SSE selection.
Mixed filters retain generic validation; malformed input never silently drops one bad family.

The first successful frame is `notifications/subscriptions/acknowledged`, listing only supported, requested,
currently readable and independently signal-permitted IDs. Missing/denied/expired/withheld identities are omitted
identically, without reasons/counts/private diagnostics. Empty accepted objects reveal no absence cause. Full acceptance
is checked again at final preparation; changed acceptance prevents acknowledgment. The complete proposed accepted
set then passes guarded disclosure and final owned readiness; a source-side revocation cannot escape in acknowledgment.
Task bodies never enter technical tracking or an asynchronous output queue. After acknowledgment, fetch a fresh full-state baseline, **including already-terminal Tasks**,
then follow current state. Changes during opening/acknowledgment/baseline are therefore observed by the next Query.

Every Task frame is `notifications/tasks` with the **flat DetailedTask** as params, not the tasks/get envelope and
not a new resultType. All outstanding public forms are retained, including partial confirmation groups. Completed
Tool `isError: true` stays completed; failed carries the original safe JSON-RPC error; intent never implies cancelled.
Public form descriptions differ from private retained runtime contracts/answers. No progress/message notifications,
input truncation, redacted-success replacement or implicit referenced-target read is introduced.

Acknowledgment, every notification and graceful completion retain the originating ID's exact string/integer type in
standard `io.modelcontextprotocol/subscriptionId` metadata. Random owner/registration identity isolates simultaneous
callers reusing IDs; correlation is not a storage key, permission or receipt. Graceful completion uses the ordinary
complete response, not Task business completion. Task notifications carry revision and subscription metadata only;
ordinary get additionally carries central server metadata. Nested original result metadata remains nested.

## State-bound revisions and consumer merge contract

Shared get results and Task notifications carry `_meta["org.fight/taskRevision"]`:

```json
{"scope":"<64 lowercase hex characters>","value":"v1:<34 lowercase hex characters>"}
```

This is server-owned namespaced metadata. Incoming metadata cannot override it; original-operation metadata never
gets promoted to this slot. Projection creates it from the same immutable committed Task row as the state, not a
second read of a version counter. Internal `Application\Mcp\Task\Projection\TaskProjection` owns bounded shared
Query-view construction and native PHP HMAC encoding; Domain Task owns coherent outstanding-input lifecycle state,
readable metadata and protected atomic lifetime/public-write/expiry evidence. Domain compares its state values and
readable metadata directly when advancing the public-write counter, never serializing a transport view or knowing
visibility-context HMAC framing. No provider/Adapter dependency or new consumer setup sequence is required for these
PHP-internal derivations. Projection still precedes GetTaskHandler's final read/whole-state checks and the unchanged
live source/guard/native-write boundary. It also binds read-only outstanding-input expiry changes at one captured Query
clock instant. Clocks and arrival order alone are **not** the comparison contract.

For one exact Task ID and equal revision scope, compare the **entire fixed-width `value` bytewise** (ASCII lexical
comparison, not numeric parsing, locale collation or timestamp comparison):

1. Larger value replaces older state. A delayed get/baseline must not overwrite a newer live frame.
2. Equal value means the **same complete public state/retention/polling metadata**, including expiry projection.
   Treat it as a duplicate, not another transition. Subscription ID and central server metadata are not Task state.
3. Smaller value is stale and must be discarded, regardless of arrival order or equal timestamps.
4. Different/missing/malformed/unrecognized scope/value is **incomparable**, never "newer". Stop the old merge
   context, independently reauthorize/reload current get, and start a new comparison context/listen. Ignore delayed
   frames from the previous context. Do not replace the new baseline using an old scope.

Common supplies this contract; it cannot control clients that ignore it or implement browser rendering. Restart is
current-state recovery, not proof that every missed transition was recovered. Live delivery itself stops on a changed
scope or regressed source instead of presenting it as a healthy continuation.

The row retains one random 128-bit lifetime incarnation and a bounded **public-write** counter, atomically updated
with every mutation. Only changes in the readable view (including lastUpdatedAt/polling advice) advance that counter;
private answers/consumption/intent that leave the view identical do not. It is distinct from the private producer
version. Each write has 32 reserved comparison slots: distinct outstanding public expiry instants supply a sub-order
of 0–16 within that write, without mutation; equal expiry instants coalesce. A later committed public write dominates
**every** earlier expiry projection, even when independent clocks straddle expiry by 1 ms. Expiry counts are never
folded into the next write counter, which could otherwise collide with a previously observed expiry revision.
No per-transition body/history ledger or read-side mutation is retained. Reconstruction/reconnect reads derive the same revision from the same lifetime and visibility context.

Scope and an independent 128-bit additive comparison-position mask are domain-separated HMAC-SHA-256 derivations keyed by the
private lifetime incarnation and trusted visibility context. The masked fixed-width 136-bit value reserves carry space,
so it preserves order without wrapping or exposing an absolute/private mutation counter. A new visibility context
changes scope **and mask**, preventing cross-epoch counter subtraction; the scope does not reveal the mask. Within
one stable permitted context, public-state differences may be coalesced and their revision distance is not private
mutation activity. Neither global storage activity nor concealed-field-only updates cause revision gaps. Consumers
must rotate epochs as above rather than promise privacy across an undisclosed visibility change.

Public-write counters are 1–2,147,483,647, with no wrap/reset. Exhausted public writes fail safely;
read-only expiry sub-order remains bounded even at the maximum counter. Reconcile exhausted writes through a
separately authorized new operation/lifetime, not a manufactured revision reset. Clock rollback
before the row's updated time fails comparison. Keep trusted clocks synchronized/nondecreasing. Physical removal
removes incarnation/counter too; separately authorized tuple recreation has a new incomparable lifetime, not resumed
history. Prefer fresh random IDs and retain the supplier's old-producer quiescence rules. Revision is neither authority,
producer expected-version, notice continuation, Workflow state nor acknowledgment of delivery/processing.

## Bounded observation, failure and recovery

- At most **16 accepted IDs**. A normal native cycle uses one current shared Query per accepted ID; opening performs
  up to three complete acceptance passes. Final preparation temporarily holds at most 16 bounded complete views for
  the opening guard (each sourced from the existing 32,768-byte state/40,960-byte retained-row bounds); later delivery
  holds one view/frame. No protected snapshot or permission survives a scheduling/write/flush pause. Only the last
  revision per ID and, for small explicit PSR reads, one frame digest/offset persist. Each fragment incurs fresh
  bounded Query/admission work and consumes the work/deadline budget. Intermediate changes may be coalesced; no
  every-update/replay promise. Duplicate-state observations still pass the current guard/readiness boundary;
  coalescing suppresses bytes, never revocation/fault checks or a safe failure before lifetime completion.
- Existing defaults: **30-second** lifetime (1–120), **200,000-microsecond** wait (10,000–1,000,000), and at most
  **12,000 work checks**. For 16 IDs, cancellation/deadline is checked between IDs; callbacks consume the finite
  lifetime. The hard check count bounds Task Queries to fewer than 12,000 per stream (plus at most 48 opening
  checks). Actual cadence includes Query/policy/tracking/write time, not guaranteed zero latency. No catch-up buffer.
- Final **encoded JSON-RPC + SSE** frame defaults to **16,384 bytes**, configurable **256–65,536**. Include nested
  result/text, escaping/multibyte characters, revision, exact typed subscription ID, envelope and `data: ` / blank-line
  framing. Ordinary get's central metadata is covered by its separate complete-response guard. A stored-valid state
  can exceed the serving budget: stop without emitting any oversized/partial frame, never truncate outstanding forms
  or a result. Acknowledgment and graceful completion use the same frame budget. Keepalive is a 13-byte comment.
- Reuse existing 256-active/4,096-retained capacity, owner-bound five-second observations, heartbeat/close/expiry and
  bounded cleanup. Store only permitted requested/accepted Task IDs and technical context, never bodies, answers,
  results, errors, revisions or JSON-RPC IDs. Ordinary subscriber inspection remains optional and independently gated.
- If a previously accepted Task becomes unavailable/denied/withheld/oversized, or its scope/source/tracking/transport
  fails, **close the entire stream**, including a mixed stream, without successful completion. Private diagnostics go
  only to redaction-aware diagnostics. Never continue as healthy idle Task coverage, infer absence causes, manufacture
  outcomes or downgrade to partial state. Technical close failure leaves last observation to expire; abrupt death is
  stale/uncertain, never confirmed connectivity. Already-sent bytes cannot be retracted.
- Lifetime completion may send graceful completion only after successful owned tracking closure. Disconnect cancels
  observation only, not durable work. Source/transport/publication failure cannot roll back outcomes, renew retention,
  consume input/confirmation, replay effects or request business cancellation. New get/listen independently recovers
  currently authorized state within retention, **not** missed frames. Expiry/disappearance stays unavailable.

All SAPI/native [subscription limitations](mcp-subscriptions.md#livenative-runtime-qualification) remain: no output
buffering/compression, no Content-Length, one frame per write/flush pause. PHP cannot preempt blocked SQL, policy,
worker or socket I/O. Consumers must bound admission, workers, SQL/network/write timeouts and proxy buffers. A stalled
reader can outlive freshness; returned callbacks must revalidate before new production. Kernel/proxy buffering and
sent bytes cannot be recalled. Paused-client proof is not saturated-kernel/instant-disconnect qualification.

## Compatibility and evidence

TASK-00188 directly removes never-tagged aggregate `view()`/`revision()` methods in favor of the internal Application
`TaskProjection` used by the existing shared Query. Protected Domain `stateAt()`, metadata and lifetime/order accessors
retain lifecycle ownership without public wire encoding; neither they nor the projection grant disclosure. Its native
PHP derivations require no encoding provider, extra handler dependency, mode flag or caller sequencing. Row schema,
revision format/width, budgets, final checks, fragment identity and guarded native handoff are unchanged. The manifest
classifies the helper internal and refreshes operation shapes, without development-snapshot aliases or migrations.
Direct `TaskProjectionTest` fixes independent HMAC/addition vectors and complete safe field/expiry behavior;
`TaskRevisionTest` continues shared-Query privacy/order/final-check proof. Later typed values remain TASK-00189's scope.

Historically, TASK-00171 added one public consumer-implementable interface, `McpTaskDisclosureGuard`, and two
internal delivery/action helpers. No endpoint, dependency, event, publisher, store or table was added. At that
checkpoint, provisional Task row schema one directly gained incarnation/public counter, and `TaskReadAdmission` gained required
`comparisonScope()`. Existing Task/TaskInputs methods and McpTasks revision/current projection are directly extended;
McpSubscriptions gains optional trailing Tasks, its accessor and the paired required disclosure guard when Tasks
are enabled; generic admission/store/inspection formats remain.
No development-snapshot aliases/migrations are supplied. Compatibility is against released tags through v1.4.0:
all Task/subscription additions are opt-in and unreleased. Existing CQRS signatures, ordinary Tool/interactive return
contracts, direct JSON, Tool progress and canonical/legacy native adapters remain. The compatibility manifest retains
existing declarations/operation promises and updates their exact operation-shape digest and new public constant.
DBAL 4.4/current public APIs and the four scoped internal-schema diagnostics remain unchanged.

`TaskRevisionTest` and `McpTaskSubscriptionsTest` qualify equal timestamps, expiry (including a failing-then-repaired independent-clock collision regression),
hidden mutations, visibility scopes, exhaustion/rollback, baseline/read/live/reconnect races, coalescing, complete supplier projections, immediate revocation,
tracking/source/transport faults, exact/over escaped final frames and cancellation at each disclosure boundary.
Regression-first REV-01/REV-02 cases revoke signal or perform real native owner close/close+cleanup during the final
opening Query, baseline and update. Guard conformance additionally exercises complete multi-ID admission, fresh whole-
state/field denial, before/during/after-writer schedules, omission/deferred/repeated/foreign-Fiber actions, action faults,
fragmented reads and exact in-guard native byte handoff.
`McpTaskLiveJourneyTest` uses actual raw-socket HTTP and independent producer PHP processes on real SQLite/MySQL/
PostgreSQL: simultaneous typed reused IDs, complete real confirmation/partial answers, cancellation intent and terminal
Tool-error/JSON-RPC-error/cancelled outcomes, reconnect, source/tracking/payload revocation, disappearance, idle closure,
abrupt death/late renewal and native PSR/Symfony/Laravel/CodeIgniter/Slim/Yii emission. Existing ordinary progress/native
journeys remain in the gate. Actual notice/tracking storage checks reject protected-state contamination; no publisher
is composed or invoked. Unit interleavings, not sleeps alone, qualify ordering and queued revocation. No persistent
HTTP stack or consumer deployment is introduced. Nonvisual wire/Query/storage traces, counts, warnings and gate receipts
are linked from TASK-00171's Engineer handoff. Independent Review and QA accepted the implementation at the pre-publication checkpoint recorded in `TASK-00171` (`planning/tasks/00171-TASK.md`); publication and consumer deployment remain separate.
