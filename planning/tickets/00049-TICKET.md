---
id: TICKET-00049
epic: EPIC-00012
title: Start durable asynchronous operations and retrieve their outcomes
status: ready-for-agent
---

# Start durable asynchronous operations and retrieve their outcomes

## Problem statement

Authorized clients need to start supported long-running operations and obtain their outcomes after disconnecting.
A transient response, queued message or durable handle alone cannot establish that a worker started, an external
side effect succeeded or an authoritative Workflow completed. Common needs a reusable durable lifecycle without
becoming the consumer's job executor.

## Solution and boundaries

Deliver the start/status/result journey of [EPIC-00012](../epics/00012-EPIC.md) using the official
`io.modelcontextprotocol/tasks` extension for `2026-07-28`, existing guarded HTTP and shared CQRS use cases.

- Enable and advertise Tasks explicitly. Check the client's extension declaration before returning a Task result.
  Supported Tool calls may return an ordinary result or a Task handle under consumer composition. Unsupported
  clients/compositions must not silently start inaccessible asynchronous work; capability declarations are not grants.
- Supply typed lifecycle contracts, handlers and actual reusable persistence for handle, state, result/error,
  outstanding input and retention metadata. Persist creation before acknowledging a handle, including extension-
  required TTL/polling information. Storage failures remain explicit; a timeout may leave commit outcome uncertain.
- Use `CommandBus::execute()` for creation, trusted producer transitions and authorized removal, and
  `QueryBus::fetch()` for retrieval. Preserve existing bus signatures. HTTP adapters must not write a competing
  job/Workflow database, change Queries into mutations or duplicate consumer policy to return a handle.
- Support `working`, `input_required`, `completed`, `failed` and `cancelled`. Validate producer transitions and
  terminal-state integrity under concurrent updates. A terminal state cannot be overwritten by a late competing
  outcome. Producer evidence, not silence or transport state, establishes the reported outcome.
- `tasks/get` returns currently authorized state and, when terminal, the original operation's result/error under
  its protocol semantics. Bound status text, input maps and encoded output; validate safe projection before disclosure.
  Protocol completion is not necessarily business success, independent review acceptance or planning TASK completion.
- Supply retention/removal mechanics under consumer-selected policy and scheduling. Expired, missing or otherwise
  unavailable state must not imply success, cancellation or a recovered result. Do not infer a particular reason
  for absence without supporting evidence. Handle possession never bypasses current permission checks.
- Polling works without Subscriptions, Mercure or execution-introspection composition. Reconnecting clients can
  retrieve retained current state without reconstructing intermediate events or keeping the original request open.
- Distinguish handle durability from dispatch, execution and external effects. Consumers own workers, job identity,
  scheduling, reconciliation and uncertain-effect recovery. No mandatory outbox, exactly-once execution or transaction
  with a consumer job system is implied. Document retry/concurrency and failure semantics without inventing them.

## Use cases

Labels describe use-case responsibilities, not final PHP class names or a selected storage schema.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Authorized client starts a supported asynchronous Tool operation | Create Task and the consumer's existing operation Commands where applicable | Current availability/access and created-state retrieval as needed | Safe lifecycle facts where defined; not a dispatch/completion guarantee | Persist Task before handle response; consumer owns execution submission |
| Trusted producer reports operation progress or outcome | Record validated lifecycle transition | Current-state checks as needed | Safe lifecycle facts where defined, distinct from application Workflow events | Persist valid state/result/error without reopening terminal state |
| Client polls or reconnects | N/A; read only | Retrieve Task through `tasks/get` | N/A; reads create no business or receipt fact | Return safe bounded current state under fresh access |
| Authorized consumer operation removes retained Task data | Remove selected Task data under supplied policy | Internal selection/access checks | Safe removal facts where defined | Remove bounded stored data without asserting cancellation or external rollback |

## Validation and permissions

Consumer composition supplies trusted caller/producer context, operation availability and current permissions.
Validate Task identity, state transitions, response shapes and finite bounds. Scope producer updates independently
of client read access; public `tasks/update` is not a producer status setter. Cross-scope, concealed, invalid or
unavailable handles receive safe outcomes without disclosing hidden results or private diagnostics. Check access on
every read and mutation, including after reconnect or policy changes. Results/errors may be protected data even when
status or a safe change reference is visible. No raw private exception, credential or permission-bearing payload
may be exposed merely because it was persisted.

Safe lifecycle fact events, if needed, use existing `EventDispatcher::trigger()` contracts. Exact event catalog is
subsequent design; events and MCP notifications are not interchangeable and do not create a Workflow authority or
mandatory Event Store. Subsequent publication failure cannot undo durable state or erase polling recovery.

## Dependencies and exclusions

Reuse [TICKET-00023](00023-TICKET.md)'s guarded HTTP, [TICKET-00024](00024-TICKET.md)'s explicit Tool integration and
current access seams. Existing `McpTool::handle()` does not return a Task today; downstream design must deliberately
classify public contract changes and preserve ordinary result/input behavior rather than assuming support exists.
Supplies the common state foundation for [TICKET-00050](00050-TICKET.md)'s requested input,
[TICKET-00051](00051-TICKET.md)'s cooperative cancellation and [TICKET-00052](00052-TICKET.md)'s optional live state.
Their client interactions remain owned there, not omitted lifecycle states here.

Exclude workers, schedulers, mandatory outbox, exactly-once jobs, generic public status mutation, Agent directory,
new permission policy, Workflow orchestration and consumer record changes. Task, consumer job, Workflow/phase and
planning TASK identities remain distinct even when linked. Exact schema, adapter selection, numeric limits,
retry mechanics and retention configuration belong downstream. No release/version assignment is made.

## Acceptance and evidence

Prove actual guarded HTTP Tool → durable handle → disconnect/reconnect → status/result journeys against real reusable
persistence and consumer execution fixtures, not a new production worker. Cover truthful negotiation/advertisement,
ordinary synchronous results, unsupported clients with no inaccessible async effects, durable acknowledgment,
storage failures and uncertain outcomes. Exercise supported states, concurrent trusted updates, terminal integrity,
current access/scope isolation and safe bounded result/error projection preserving original operation semantics.
Verify retention/removal, unavailable state and polling without Subscriptions/Mercure. Distinguish handle creation
from dispatch/execution evidence and state persistence from later publication outcomes.

Each implementation TASK owns relevant behavior tests, exact owned-code coverage, compatibility classification and
the complete local `./bin/build` gate under the [project profile](../agents/project-profile.md). Document composition,
public contracts, lifecycle, permissions, retention, limits and failure/retry behavior; align CONTEXT.md when
implemented. These are future obligations, not checks already run or actual Agent OS/Pi deployment qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00166](../tasks/00166-TASK.md) | Record and retrieve durable asynchronous Task state | in-progress |
| [TASK-00167](../tasks/00167-TASK.md) | Remove retained asynchronous Task data safely | ready-for-agent |
| [TASK-00168](../tasks/00168-TASK.md) | Start and recover asynchronous operations through MCP | ready-for-agent |
<!-- /planning:children -->

## Decisions and planning approval history

John approved this first requirement area in EPIC-00012's complete four-TICKET split. It preserves
[WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md)'s durable-handle
boundary and [ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md)'s separation of transport
submission from execution/outbox guarantees. Optional Event Store/projection contracts are not automatically adopted.
`ready-for-agent` records accepted requirements, not implementation acceptance.

John subsequently approved the complete three-TASK split and cross-ticket activation/evidence sequencing:

| Accepted TASK | Outcome | Blockers |
| --- | --- | --- |
| [TASK-00166](../tasks/00166-TASK.md) | Record and retrieve durable asynchronous Task state through shared CQRS and actual DBAL | None unfinished |
| [TASK-00167](../tasks/00167-TASK.md) | Independently authorized bounded removal and truthful shared reads | TASK-00166 |
| [TASK-00168](../tasks/00168-TASK.md) | Complete guarded Tool-to-handle/reconnect journey and extension activation | TASK-00166, TASK-00169, TASK-00170 |

The [released specification](https://github.com/modelcontextprotocol/ext-tasks/blob/main/specification/2026-07-28/tasks.md)
and [schema](https://github.com/modelcontextprotocol/ext-tasks/blob/main/schema/2026-07-28/schema.ts) expose one empty
extension declaration, not a polling-only setting; cancellation acknowledgment is required. Approved sequencing:
TASK-00166 supplies the usable shared lifecycle; TICKET-00050/00051 supply input/cancellation behavior and method handling
without waiting for TASK-00168; TASK-00168 consumes their real implementations, activates the complete extension and
owns combined Tool-start/input/cancellation HTTP evidence. Supplier TASKs own focused shared-use-case/method proof.
This explicitly allocates the combined evidence from those TICKETs without dropping it or creating a circular gate.
TASK-00169 supplies input; TASK-00170 supplies cancellation and actual input-intent race integration. Both are now
approved/written blockers on TASK-00168 alongside TASK-00166. All supplier identities are resolved; TASK-00168 is
ready-for-agent but still waiting on implementation. TASK-00170 consumes TASK-00169 without a reverse dependency.

Approved shared direction: real SQLite/MySQL/PostgreSQL state/result/error/input/retention persistence and direct current
reads; application-generated identity preserves void Commands. Actual commit precedes acknowledgment, with supported
transaction ownership, rollback/uncertainty and later publication failure distinguished. Current creation, trusted
producer update and protected-read decisions are separate. Atomic state/payload writes and race-safe transitions
preserve terminal winners; timestamps alone are not concurrency fences. Define same-content retries/conflicts and
bounded comparison lifetime without a worker/outbox/exactly-once claim. Removal separately owns atomic selected data/
comparison deletion, repeated/missing behavior and races preventing silent resurrection. TTL is duration from creation,
not an inactivity timeout or proof of deletion/cancellation. TASK-00167 and TASK-00168 are independent; protocol absence
fixtures do not replace TASK-00167's actual removal/Query proof.

Approved protocol direction: negotiate before asynchronous effects; non-declaring clients needing Tasks receive -32021.
Preserve ordinary Tools and classify McpTool/McpResult public changes deliberately. Creation is flat resultType:task;
get is resultType:complete with detailed state. Mcp-Name mirrors taskId for get/update/cancel. Completed includes Tool
isError:true; failed contains a safe JSON-RPC execution error. Bound the final encoded envelope including nested results,
text companions, escaping and central metadata; do not truncate outstanding input or fabricate a successful redacted
result. Original connection loss is not durable cancellation. Real guarded HTTP and three-database consumer execution
fixtures prove package behavior, not deployed Agent OS support. Exact APIs/schema/limits/retry mechanics remain
implementation details; each TASK owns compatibility/docs/CONTEXT.md, exact coverage and full local build.

No global execution priority, release target or checkout/worktree choice is assigned. Choose checkout/worktree explicitly
before implementation. The complete decomposition is accepted/written; TASK-00168's complete dependency identities are resolved, not implemented.

All four map EPICs have accepted, written TICKET decompositions. TICKET-00040's complete three-TASK split is now
accepted and written as TASK-00147–00149. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00041's complete split is accepted/written
as TASK-00150–00152; TICKET-00042's complete split is accepted/written as TASK-00153–00154. TICKET-00043's complete
MCP split is also accepted/written as TASK-00155–00156. TICKET-00044's complete single-TASK decomposition is
accepted/written as TASK-00157. No implementation, consumer amendments, commit or
publication have occurred.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

TICKET-00048's complete optional-Mercure TASK-00165 is accepted/written, completing EPIC-00011's TASK planning.
TICKET-00050/00051/00052's TASK-00169/00170/00171 are complete approved/written decompositions; all map planning is complete. Ordinary asynchronous
Task polling gains no dependency on Subscriptions/Mercure.

## Current implementation handoff

TASK-00166 now supplies the reusable lifecycle, direct three-database persistence and protected current-state Query,
with local verification recorded in its [Work checkpoint](../tasks/00166-TASK.md#work-implementation-checkpoint).
Independent Review and QA remain pending; the TICKET is not implementation-complete. Removal and complete MCP activation
remain with TASK-00167/00168 and their actual suppliers. No Tasks extension advertisement or consumer runtime qualification
follows from the lifecycle alone. The statements above about no implementation/publication describe planning approval,
not the current Task implementation checkpoint.

Next: `/skill:review TASK-00166` in an independent session.
