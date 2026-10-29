---
id: TICKET-00050
epic: EPIC-00012
title: Supply requested input to asynchronous operations
status: ready-for-agent
---

# Supply requested input to asynchronous operations

## Problem statement

An asynchronous operation may need supported client input after its original connection has closed. Clients must
be able to retrieve and answer retained requests under current permissions without changing arbitrary Task state
or accidentally applying the same accepted input twice. Existing stateless ordinary input alone does not provide
that durable Task interaction contract.

## Solution and boundaries

Deliver [EPIC-00012](../epics/00012-EPIC.md)'s input journey on [TICKET-00049](00049-TICKET.md)'s durable lifecycle.

- Trusted producer operations retain outstanding requests and expose `input_required` with the extension's keyed
  `inputRequests` representation. Requests remain available through authorized Task reads across reconnects within
  retention, independent of live notification availability.
- Map public `tasks/update` to shared input-response Commands. It supplies responses to outstanding requests; it is
  not a generic state setter, result uploader, permission grant or direct worker-execution endpoint.
- Follow the extension's response handling: validate active responses and ignore unknown/already-satisfied keys.
  Validate the overall protocol envelope and bounds; ignored keys do not authorize processing or waive checks on
  active responses. Acknowledge according to the extension rather than inventing a completed business result.
- Preserve request identity and accepted-response integrity under repeat/concurrent submissions. Do not accidentally
  apply the same accepted input twice. Coordinate state changes with completion and cancellation so late responses
  cannot reopen terminal Tasks, bypass cancellation outcomes or continue work under revoked authority.
- Reuse the existing supported form-input and destructive-confirmation protections where applicable. Common supplies
  durable response admission and validation; consumers own the meaning of answers and whether/how work continues.
  Preserve caller/operation/request binding and destructive-action controls rather than treating Task access as
  business approval. Acceptance of an answer is not proof of execution or an exactly-once external effect.
- Distinguish new durable Task input behavior from ordinary stateless interaction: this work does not silently make
  every ordinary input token replay-protected or weaken the existing confirmation contract. Design and document the
  reuse/adaptation seam explicitly, including failure after admission and uncertain continuation.

## Use cases

Operation labels name responsibilities, not final PHP methods or a prescribed admission algorithm.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Trusted operation requests supported input | Record outstanding input through the shared lifecycle | Current-state checks | Safe input/lifecycle facts where needed, not business approval | Persist requests and a valid `input_required` state |
| Client retrieves requests after reconnect | N/A; read only | TICKET-00049's `tasks/get` Query | N/A; reads are not receipt facts | Return currently permitted outstanding requests within retention |
| Authorized client answers active requests | Submit Task input responses through `tasks/update` | Active-request/current-access checks | Safe accepted-input facts where defined; consumer business events remain separate | Persist validated admission and expose it to consumer continuation without duplicate application |
| Client repeats a response or submits an unknown key | Same input Command | Request/admission checks | No invented acceptance or completion event for an ignored key | Follow protocol ignore behavior without redispatching satisfied input |
| Input races completion or cancellation | Same input and existing lifecycle Commands | State checks | Facts reflect actual accepted outcomes only | Preserve terminal integrity and prevent unauthorized/duplicate continuation |

## Validation and permissions

Use trusted consumer context for both producer input requests and client responses. Recheck current access before
request disclosure, active-response validation/admission and authorized continuation as applicable. A Task handle,
previous input request, accepted answer or client capability is not an independent permission grant. Keep denied
and concealed outcomes safe, including protected request schemas/content and diagnostics.

Validate active request/response shapes, supported form/confirmation types, action values, schema content and finite
input/encoded-output bounds. Retain existing destructive confirmation rules, including single-use admission and safe
refusal handling; do not interpret decline/cancel as affirmative permission. Input-level refusal is not automatically
a `tasks/cancel` operation or proof that an external job stopped. Exact supported request reuse, transaction and
concurrency mechanisms remain downstream design inside these guarantees.

## Dependencies and exclusions

Depends on TICKET-00049's storage, lifecycle and current-state reads. Reuse
[TICKET-00026](00026-TICKET.md)'s supported input/confirmation boundaries without changing its deliberately replayable
ordinary stateless input semantics or assuming its consumer-provided confirmation store already implements durable
Task storage. Coordinate input/cancellation race evidence with [TICKET-00051](00051-TICKET.md); this does not require
circular implementation blockers. [TICKET-00052](00052-TICKET.md) separately presents these states in optional live
notifications; input must work through polling without it.

Exclude URL-mode interaction, unrestricted remote Tool execution, new business-approval policy, generic public
status mutation, job execution machinery and exactly-once external effects. Lifecycle fact events, if needed, use
existing dispatch contracts and safe metadata; no Event Store, outbox or Workflow authority is implied.

## Acceptance and evidence

**Approved sequencing allocation from TICKET-00049:** TASK-00166 supplies the shared lifecycle. This TICKET's approved
TASK-00169 owns real input admission/continuation mechanics and method handling, with focused shared-use-case/method
proof; it does not depend on TASK-00168. TASK-00168 consumes these implementations and the cancellation supplier, owns complete
extension activation and the combined Tool-start HTTP journey below. That evidence remains required there, not waived
or made a circular completion gate here. TASK-00169 and cancellation supplier TASK-00170 are now actual blockers on
TASK-00168 alongside TASK-00166. Its needs-info condition is resolved; implementations remain unfinished.

Demonstrate guarded HTTP start → retained input request → disconnect → `tasks/get` → `tasks/update` → observed
continuation/outcome using real reusable Task persistence and consumer fixtures. Cover valid supported input,
invalid active content, malformed/oversized envelopes, unknown/already-satisfied keys and mixed active/ignored
responses. Prove current permission checks, scope isolation, protected request disclosure and safe absence/expiry.
Exercise repeated/concurrent submissions and races with terminal producer updates/cancellation, including failures
after admission, without accidental double application or false exactly-once effect claims. Prove destructive
confirmation/refusal protections remain intact and ordinary stateless interaction semantics are unchanged.

Document supported requests, validation, admission/continuation limits, retry/failure behavior and consumer policy
ownership; align CONTEXT.md when implemented. Each implementation TASK owns relevant behavior tests, exact owned-code
coverage, public compatibility classification and the complete local `./bin/build` gate under the
[project profile](../agents/project-profile.md). These are future checks; actual client deployment qualification is
separate and no implementation acceptance is claimed.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00169](../tasks/00169-TASK.md) | Supply and admit durable Task input | in-progress |
<!-- /planning:children -->

## Decisions and progress

John approved this second requirement area in EPIC-00012's four-TICKET split. The
[official Tasks overview](https://modelcontextprotocol.io/extensions/tasks/overview) and
[research](../wayfinder/research/mcp-prompts-skills-jobs-and-live-updates.md#4-tasks-fit-asynchronous-jobs-execution-remains-separately-owned)
support keyed input responses rather than public status setting. `ready-for-agent` records accepted requirements.
All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
and written as TASK-00147–00149. No implementation, consumer amendments, commit or
publication have occurred. Follow the [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff)
for Board-based execution selection after complete map-wide decomposition through TASK-00171, not automatic execution. TICKET-00041's
complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as
TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

TICKET-00049's complete TASK-00166–00168 split and cross-ticket activation/evidence sequencing are approved/written.
TASK-00166 is the shared supplier; TASK-00167 removal is independent. Do not add a reverse input dependency on final
activation TASK-00168; its full blocker allocation is now resolved without changing its approved scope.

John subsequently approved this TICKET's complete single-TASK decomposition, now written as
[TASK-00169 — Supply and admit durable Task input](../tasks/00169-TASK.md), blocked only by TASK-00166.
Ordinary forms, destructive-confirmation safeguards, real DBAL admission and actual tasks/update handling stay together.

Approved mechanics: extend the existing durable lifecycle rather than another interaction store. Bind immutable request
keys for the whole Task lifetime, including answered/superseded requests, with finite bookkeeping/exhaustion behavior.
Support partial maps; validate all active entries before atomic batch admission, retain remaining outstanding input,
and ignore unknown/answered/superseded keys without bypassing envelope/access/bounds checks. Adapt existing form/schema/
action validation deliberately: McpInputResponses currently requires an exact complete key set, and its ordinary
stateless behavior must remain unchanged. Retain private rules/bindings separately from disclosed form descriptions.

Durable acceptance precedes acknowledgment; repeated/competing responses cannot replace answers, redispatch them or
reopen terminal work. Expose accepted input through an authorized continuation contract; consumers own execution,
scheduling and uncertain-effect recovery. Bind confirmation caller/Task/operation/original arguments/request/group/
expiry. Partial accepts authorize no destructive continuation; every required confirmation must accept, while any
refusal retires that group without destructive continuation or automatic whole-Task cancellation. Failure after admission
never restores consumed confirmation. No second consumer store or cross-store atomicity is assumed.

Current producer/read/admission/continuation decisions remain separate; forms/answers/private diagnostics stay protected.
TASK-00169 supplies per-request capability checks, bounded parameters, shared Commands and safe tasks/update ack/errors,
without independent extension advertisement. It owns real SQLite/MySQL/PostgreSQL issuance/read/admission/observation
and method evidence, including input versus producer terminal races. TICKET-00051 owns actual cancellation-intent/input
races; TASK-00168 owns complete activation and combined Tool-start HTTP journeys. Its input blocker is now TASK-00169;
cancellation blocker TASK-00170 is also resolved and owns actual input-intent integration against this supplier. No reverse activation/removal/live/Mercure dependency is introduced.

Exact APIs/schema/limits/adaptation mechanisms remain implementation details. The TASK owns compatibility/docs/CONTEXT.md,
exact coverage and full build. No global priority, release target or worktree choice is assigned; choose checkout/worktree
explicitly before implementation. Decomposition is complete, not implementation acceptance. TICKET-00051's TASK-00170 and TICKET-00052's TASK-00171 are complete approved/written decompositions; all map planning is complete.

Next: `/skill:next`
