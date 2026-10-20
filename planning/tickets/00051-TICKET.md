---
id: TICKET-00051
epic: EPIC-00012
title: Request cooperative cancellation of asynchronous operations
status: ready-for-agent
---

# Request cooperative cancellation of asynchronous operations

## Problem statement

A client may no longer want a long-running operation to continue. Acknowledging that request must not falsely
claim the worker stopped, erase a completed result or imply rollback of external effects. Cancellation must remain
correct when it races input, completion or failure and when callers repeat the request.

## Solution and boundaries

Deliver the authorized cancellation journey of [EPIC-00012](../epics/00012-EPIC.md) using the official Tasks extension
and [TICKET-00049](00049-TICKET.md)'s durable state contract.

- Route `tasks/cancel` to a shared cancellation-request Command under current consumer authorization. Follow the
  extension's acknowledgment and repeated/terminal-request behavior; a protocol acknowledgment represents intent,
  not a forced `cancelled` outcome or success of a business compensation.
- Make the request available to the consumer execution owner through the reusable lifecycle contract. Consumers
  decide whether/how to stop work safely and report their actual result through trusted producer transitions.
  Exact request persistence, observation and dispatch seams are downstream design, not a new Common worker service.
- Preserve actual terminal outcomes when work completes/fails before cancellation takes effect. Do not overwrite
  terminal state with `cancelled`, revive completed work or manufacture a stop from uncertain worker evidence.
- Make repeated requests and races with input/producer transitions safe and protocol-correct. Cancellation admission
  and subsequent consumer effects remain distinct; no exactly-once job-control or external-effect claim is added.
- Use the existing Task Query behind `tasks/get` to inspect the result. A disconnected request/notification stream,
  handle expiry or cleanup is not cancellation. Polling and cancellation work without Subscriptions or Mercure.

## Use cases

Operation labels name responsibilities rather than final APIs or a chosen concurrency mechanism.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Authorized client requests cancellation | Request Task cancellation through `tasks/cancel` | Current-state/access checks | Safe cancellation-request facts where defined; not a stopped-job fact | Record/expose intent through the shared lifecycle and acknowledge per protocol |
| Consumer execution owner observes the request | Consumer-owned stop operation, not a new Common job executor | Observe cancellation intent via the lifecycle contract | Consumer application facts only when actually established | Attempt safe cooperative stopping under consumer policy |
| Trusted producer reports cancellation or another actual outcome | TICKET-00049's validated lifecycle update | Current-state checks | Safe outcome facts where defined, distinct from the request | Persist actual outcome without overwriting a terminal state |
| Client repeats cancellation or races input/completion | Same cancellation Command and existing lifecycle operations | Current-state checks | No fabricated extra stop/completion evidence | Preserve protocol behavior and a consistent terminal outcome |
| Client checks whether work actually stopped | N/A; read only | TICKET-00049's `tasks/get` Query | N/A; read only | Return currently authorized actual state, not an optimistic interpretation of acknowledgment |

## Validation and permissions

Current supplied permission to cancel is separate from possession of the handle or permission to read it. Validate
request shape, identity, scope and lifecycle conditions without leaking concealed Task state. Trusted producer
updates retain their own admission requirements; public cancellation must not act as a generic status setter.
No cancellation request grants worker execution, compensation or destructive-action authority beyond consumer policy.

Handle unavailable/expired state and late requests truthfully under the extension. Do not infer a job outcome from
missing state. Bound request/status/error data and keep private diagnostics safe. Safe lifecycle events, if needed,
use existing dispatch contracts; protocol acknowledgment is not a domain event proving cancellation. Delivery
failure or uncertain external stopping cannot undo a durable terminal result or justify claiming a rollback.

## Dependencies and exclusions

Depends on [TICKET-00049](00049-TICKET.md)'s lifecycle, storage, trusted updates and reads. Coordinate interaction
races with [TICKET-00050](00050-TICKET.md)'s requested-input flow; downstream TASK ordering must assign the integrated
race evidence without inventing circular prerequisites. [TICKET-00052](00052-TICKET.md) optionally presents actual
cancellation-related state changes; notifications are not required for this journey.

Existing [TICKET-00025](00025-TICKET.md) request/disconnect cancellation is a different transport lifetime, not this
durable operation contract. Exclude forced termination, rollback/compensation implementation, treating disconnect
or expiry as cancellation, worker/scheduler implementation, exactly-once external effects, new permission policy,
Workflow transitions and automatic release/deployment authority. Exact concurrency and cancellation-observation
mechanics remain downstream design within the accepted guarantees.

## Acceptance and evidence

**Approved sequencing allocation from TICKET-00049:** TASK-00166 supplies the shared lifecycle. This TICKET's approved
TASK-00170 owns real cancellation intent/observation and method handling, with focused shared-use-case/method proof;
it depends on TASK-00166 and TASK-00169, never TASK-00168. TASK-00168 consumes actual input/cancel suppliers and owns complete extension activation
and combined Tool-start/cancellation HTTP evidence below. Preserve that required evidence there rather than imposing
a circular completion gate here. TASK-00170 is now an actual blocker on TASK-00168 alongside TASK-00166 and TASK-00169;
all supplier identities are resolved and TASK-00168 is ready-for-agent but still implementation-blocked. TASK-00170
owns actual cancellation-intent versus TASK-00169 input-admission race evidence, without a reverse input blocker. TASK-00169 covers input versus producer terminal transitions; TASK-00168 owns the
combined final HTTP journeys.

Prove the actual guarded HTTP cancellation request and subsequent status retrieval against real Task persistence
and consumer execution fixtures. Exercise requests before and during work, repeats, terminal Tasks, absent/expired
handles, revoked cancellation permission and cross-scope access. Demonstrate both honored cancellation and work
that completes/fails before stopping; acknowledgment must not force a cancelled result. Race cancellation with
producer completion/failure and active input submission without overwriting terminal state or double-applying input.
Distinguish request admission, consumer observation, actual stopping and external effects in failure evidence.
Verify disconnect/expiry do not silently cancel and the complete journey works without live subscriptions.

Document acknowledgment semantics, consumer stopping responsibilities, permission boundaries, races and failure/
recovery behavior; align CONTEXT.md when implemented. Each implementation TASK owns relevant behavior tests, exact
owned-code coverage, compatibility classification and the complete local `./bin/build` gate under the
[project profile](../agents/project-profile.md). These are future acceptance obligations, not implementation or
consumer deployment evidence already obtained.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00170](../tasks/00170-TASK.md) | Request and observe cooperative Task cancellation | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this third requirement area in EPIC-00012's four-TICKET split, preserving cooperative rather than
forced cancellation. The [official Tasks overview](https://modelcontextprotocol.io/extensions/tasks/overview)
and [WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md) support that
boundary. `ready-for-agent` records accepted requirements only. All map TICKET decompositions are accepted and
written. TICKET-00040's complete three-TASK split is now accepted and written as TASK-00147–00149. No implementation,
consumer amendments, commit or publication have occurred.

Follow the [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) into Board-based execution
selection after complete map-wide decomposition through TASK-00171, not automatic execution. TICKET-00041's complete split is
accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as TASK-00153–00154.
TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

TICKET-00049's complete TASK-00166–00168 split is approved/written. Shared lifecycle TASK-00166 is the supplier;
TASK-00167 removal is independent, and TASK-00168 waits for actual input/cancel suppliers before complete activation.

TICKET-00050's complete TASK-00169 is approved/written, blocked only by TASK-00166. It owns retained form/confirmation
input and method handling. John subsequently approved this TICKET's complete single-TASK decomposition, now written as
[TASK-00170 — Request and observe cooperative Task cancellation](../tasks/00170-TASK.md). Blockers are TASK-00166 and
TASK-00169: actual input-admission/confirmation integration and race evidence make the latter a true dependency.

Approved direction: extend existing durable state with bounded cancellation intent distinct from actual outcome.
Commit before acknowledgment; expose intent through currently authorized shared reads across processes without
consuming it. Stale input/producer writes cannot erase intent. Reauthorize repeats; do not create another stop obligation,
renew retention or reset original admission evidence. Known authorized terminal Tasks receive successful no-op
acknowledgment, preserving their result/error. This is a package choice permitted by the released contract, not an
assertion that the protocol mandates terminal rejection or no-op handling.

Intent creates no cancelling protocol state, forced cancelled outcome, input refusal or cleared outstanding requests.
Input admission preserves intent and accepted answers; continuation exposes current intent to the consumer stopping
decision. Actual terminal winners cannot be reopened; late cancellation cannot restore consumed confirmation or erase
prior effects. Consumers decide whether/when stopping is safe and report actual completed/failed/cancelled outcomes.
No network/job-control operation spans the admission transaction, no worker/outbox/compensation is added, and no exactly-once
external stopping or eventual cancellation is promised. Missing/expired state cannot be recreated or interpreted as stop.

TASK-00170 supplies actual tasks/cancel handling with per-request capability checks before mutation, bounded parameters,
shared Commands, safe errors and empty resultType:complete acknowledgment. It does not independently advertise the
extension or use notifications/cancelled. Existing request-scoped Tool/stream cancellation remains unchanged.
Cancellation, read, producer and worker-control permissions remain independent and current, including repeats/terminal
no-ops; private state/diagnostics cannot leak through acknowledgment, intent observation or failure mapping.

TASK-00170 owns real SQLite/MySQL/PostgreSQL cancellation/observation/outcome and method evidence, including actual
TASK-00169 input/confirmation races. TASK-00168 owns complete activation/guards/mirrors/final serving envelope and combined
Tool-start/cancel/get HTTP journeys. It now has the full blocker set TASK-00166, TASK-00169, TASK-00170 and is ready-for-agent,
not implemented or executable with unfinished blockers. No reverse activation/removal/live/Mercure dependency is added.

Compatibility/schema effects, docs/CONTEXT.md, exact coverage and full build stay in TASK-00170. Exact APIs/fields/limits/
versioning remain implementation details; no global priority/release target/worktree choice is assigned. TICKET-00052's complete TASK-00171
is approved/written; all map planning is complete. Choose checkout/worktree explicitly before separately authorized implementation.

Next: `/skill:next`
