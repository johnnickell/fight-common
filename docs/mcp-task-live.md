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
instance as the optional trailing `tasks:` collaborator of `McpSubscriptions`, then pass the subscriptions to the
registry's third argument. For Task-only composition:

```php
$subscriptions = new McpSubscriptions(
    $queries,
    $tracking,
    $subscriptionAdmission,
    $trustedTrackingSelection,
    tools: false,
    resources: false,
    maxFrameBytes: 65536,
    tasks: $tasks
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
No presenter SQL, local event listener, general retained feed, Mercure publisher or captured Task history supplies
state. Task-only and empty accepted streams do **not** call `RecoverNotices`; notice storage/global evidence permission
is not a prerequisite. Enable Tools/Resources/Prompts separately for mixed streams: only accepted requested generic
families engage their existing qualified notice source. A mixed stream then inherits that source's continuity rules.
No Prompt or operator-inspection capability is required. Registry composition rejects missing/mismatched Tasks.

`McpSubscriptionAdmission::allows(subscriber, 'taskIds', taskId, null)` independently grants the **whole** technical
signal/ID at opening and each disclosure. The existing `$uri` argument carries the exact Task ID for this filter;
there is no Resource read. Fresh `TaskReadAdmission::allowsRead()` and `TaskPayloadPolicy::mayDisclose()` additionally
protect complete state and individual input/result/error/status fields through the shared Query. General notices,
Tool/catalog permission, recipients, a previous get, an acknowledgment or a privilege label grant none of these.
All callbacks must be bounded and use current trusted request context. No transaction spans stream/network waits.

`TaskReadAdmission::comparisonScope(scope, id)` is a required **trusted visibility context**, not a permission:
return a stable bounded reference (the ordinary 1–96 ASCII reference contract) binding caller, scope and visibility
policy epoch. It must agree across processes/get/listen/reconnect while the same complete view remains eligible.
Rotate it after any visibility discontinuity, including revocation/regrant or a change in eligible fields/context;
never reuse an old epoch. Consumers own policy-epoch durability and detecting those changes. If that binding is
unknown, fail comparison/read safely instead of inventing a stable scope or sharing another caller's context.
The returned reference itself is never disclosed. Get checks current read and whole-state disclosure again after
source, validation, projection, clock and comparison-context work; opening never freezes authority.

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
is checked again after final tracking callbacks; changed acceptance prevents acknowledgment. Task bodies are not queued
by filter acceptance. After acknowledgment, fetch a fresh full-state baseline, **including already-terminal Tasks**,
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
second read of a version counter. It also binds read-only outstanding-input expiry changes at one captured Query
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

- At most **16 accepted IDs**. Each cycle has at most one current shared Query per accepted ID; opening performs
  up to three complete acceptance passes. Every disclosure checks signal permission and the shared Query **after**
  owner renewal. There is no protected snapshot queue across tracking or write/flush pauses. Only the last revision
  per ID is remembered, never full bodies. Intermediate changes may be coalesced. No every-update/replay promise.
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

No new production declaration, endpoint, dependency, event, publisher, store or table is added. Existing provisional
Task row schema one directly gains incarnation/public counter, and provisional `TaskReadAdmission` gains required
`comparisonScope()`. Existing Task/TaskInputs methods and McpTasks revision/current projection are directly extended;
McpSubscriptions gains optional trailing Tasks and its accessor; generic admission/store/inspection formats remain.
No development-snapshot aliases/migrations are supplied. Compatibility is against released tags through v1.4.0:
all Task/subscription additions are opt-in and unreleased. Existing CQRS signatures, ordinary Tool/interactive return
contracts, direct JSON, Tool progress and canonical/legacy native adapters remain. The compatibility manifest retains
existing declarations/operation promises and updates their exact operation-shape digest and new public constant.
DBAL 4.4/current public APIs and the four scoped internal-schema diagnostics remain unchanged.

`TaskRevisionTest` and `McpTaskSubscriptionsTest` qualify equal timestamps, expiry (including a failing-then-repaired independent-clock collision regression),
hidden mutations, visibility scopes, exhaustion/rollback, baseline/read/live/reconnect races, coalescing, complete supplier projections, immediate revocation,
tracking/source/transport faults, exact/over escaped final frames and cancellation at each disclosure boundary.
`McpTaskLiveJourneyTest` uses actual raw-socket HTTP and independent producer PHP processes on real SQLite/MySQL/
PostgreSQL: simultaneous typed reused IDs, complete real confirmation/partial answers, cancellation intent and terminal
Tool-error/JSON-RPC-error/cancelled outcomes, reconnect, source/tracking/payload revocation, disappearance, idle closure,
abrupt death/late renewal and native PSR/Symfony/Laravel/CodeIgniter/Slim/Yii emission. Existing ordinary progress/native
journeys remain in the gate. Actual notice/tracking storage checks reject protected-state contamination; no publisher
is composed or invoked. Unit interleavings, not sleeps alone, qualify ordering and queued revocation. No persistent
HTTP stack or consumer deployment is introduced. Nonvisual wire/Query/storage traces, counts, warnings and gate receipts
are linked from TASK-00171's Engineer handoff; independent Review and QA remain separate acceptance stages.
