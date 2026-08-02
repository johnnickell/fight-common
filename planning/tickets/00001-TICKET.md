---
id: T-00001
prd: PRD-00001
title: Evaluate a consumer migration pilot
status: done
blocked_by:
---

# Evaluate a Consumer Migration Pilot

## What to Build

Compare Fight CMS, the current Fight project template, and Omphalos against the accepted Event Sourcing contracts. Deliver one evidence-backed pilot recommendation with a bounded aggregate, migration sequence, rollback boundary, operational risks, and explicit exclusions. Consumer implementation remains in its owning repository, and this evaluation does not block 1.2.

## Blocked By

None — can start immediately.

## Acceptance

- [x] Candidate seams and current event flows are evidence-backed.
- [x] All three candidates are assessed against the same accepted contracts.
- [x] One pilot is recommended with reasons, a migration sequence, a rollback boundary, risks, and explicit exclusions.
- [x] Unknowns are identified rather than inferred as absent behavior.
- [x] Consumer changes remain in their owning repository.
- [x] The recommendation remains non-release-blocking for Fight Common 1.2.

## Evaluation Basis

The evaluation used the accepted Fight Common contracts in
[`CONTEXT.md`](../../CONTEXT.md),
[`ADR-0001`](../adr/0001-event-sourcing-contract.md), and
[`ADR-0002`](../adr/0002-projection-delivery.md). Each consumer was assessed for the same concerns:
aggregate boundary, current source of truth, event flow, history value, concurrency, read-state needs,
side effects and recovery, migration cost, rollback, and fit with the accepted contracts.

Evidence was read from committed source at these snapshots on 2026-08-01. Unrelated local changes in the
consumer worktrees did not touch the evaluated seams.

| Consumer | Candidate boundary | Commit | Branch at evaluation |
| --- | --- | --- | --- |
| Omphalos | Metis `Operation` | `2695550fabb5e3422cb3d60d7fa51514160da3c3` | `feature/t-00007-resumable-supported-extraction-failures` |
| Fight CMS | Content Management `Post` | `f5bbd7e554500cbdebba8f0c027f22213ff488a9` | `feature/theme-designer-control-surface` |
| Fight project template | Access Control `User` | `c82e942668ad6b5975a3d9fa395631d3936dbb1c` | `feature/agent-management` |

## Comparison

| Criterion | Omphalos: Metis `Operation` | Fight CMS: `Post` | Fight project template: `User` |
| --- | --- | --- | --- |
| Aggregate boundary | One governed-operation lifecycle owns request policy, approval or rejection, execution, completion or failure, and halt refusals. | One editorial lifecycle owns content and status, but also holds mutable `Site`, `Category`, `Tag`, and `Link` relationships. | One identity lifecycle owns registration, roles, password, and reset-token state while also implementing Symfony security contracts. |
| Current source of truth | Mutable `metis_operations` ORM rows queried through `OperationRepository`. | Mutable `posts` ORM rows plus link, site, category, and tag relations queried through `PostRepository`. | Mutable `users` ORM rows plus role relations and reset-token fields queried through `UserRepository`. |
| Current event flow | Request commits without a success event. Approval and rejection commit before dispatch. Execution commits `Executing` before the external handler; completion and failure dispatch before their final row commit. `EventDispatcher` resolves to the asynchronous Messenger dispatcher. | Command handlers mutate and commit before synchronously dispatching lifecycle events. `AuditSubscriber` derives audit entries from those events. Events are handler-created notifications, not aggregate-owned recorded history. | Command handlers mutate and commit before synchronously dispatching user events. Registration and password-reset-request events invoke mail subscribers, and the audit subscriber records them. |
| Historical value | High. Governance decisions, policy snapshots, actors, execution attempts, outcomes, and refusal reasons are the product's audit evidence. | Moderate. Editorial history is useful, but authoritative content is primarily the current publication state and existing audit entries already provide some history. | Low for a first pilot. Security state needs current correctness, while most queries and authorization checks need the current user row rather than replayable projections. |
| Concurrency | No aggregate version or optimistic-lock field exists. Competing approval, rejection, or execution decisions can therefore validate Event Store expected-version enforcement. | No aggregate version or optimistic-lock field exists. Concurrent editing matters, but the coupled content graph makes the first migration broad. | No aggregate version or optimistic-lock field exists. Email uniqueness supplies a database constraint, but event sourcing adds little representative projection value. |
| Read state | Pending, ready, detail, outcome, and filtered-history views are already explicit and can become rebuildable projections. | Admin and public reads use the same ORM aggregate and its related content objects; separating a write model from those reads would touch routing and publication paths. | Authentication, authorization, administration, and password reset read the ORM entity directly; a projection split would affect security-critical paths. |
| Side effects and recovery | External operation handlers run after the durable `Executing` transition. A crash after an effect but before final status commit can leave an abandoned execution requiring identity, reconciliation, and safe retry. | Publication changes public visibility; audit subscribers are downstream of commit. The candidate has fewer external effects but a larger content-consistency surface. | Welcome and password-reset email are non-transactional subscriber effects. Credential and reset-token data also require stricter secrecy and retention treatment. |
| Contract fit | Strong: compact history, explicit invariants, consumer-owned `OperationId`, natural projections, consequential concurrency, and meaningful recovery behavior. | Partial: rich event vocabulary, but existing events and cross-aggregate references are not a self-contained reconstitution contract. | Weak: existing events intentionally omit authoritative credential facts, and the aggregate mixes framework and persistence concerns that would obscure the shared library pilot. |
| Verdict | **Recommend as the one pilot.** | Reject as the migration pilot. Reconsider only through separately owned CMS planning. | Reject as the migration pilot. Keep the template small and adopt proven integration later. |

## Evidence

### Omphalos

- `src/Domain/Metis/Operation/Operation.php` enforces the pending-approval, ready, executing, completed,
  failed, and rejected transitions and retains policy, requester, outcome, timestamp, and halt-refusal data.
- `src/Application/Metis/Operation/CommandHandler/*.php` and
  `src/Application/Metis/Operation/Service/OperationDispatcher.php` show the current commit and dispatch
  ordering, including the durable `Executing` transition before an external handler is invoked.
- `src/Domain/Metis/Operation/Event/*.php` contains approval, rejection, execution-started, completed, and
  failed events, but no successful request event or halt-refusal event. The payloads must be audited before
  they can become complete reconstitution facts.
- `src/Adapter/Repository/Metis/DoctrineOperationRepository.php` and
  `database/schemas/Metis.Operation.Operation.orm.xml` establish the mutable row source of truth and show no
  aggregate version field.
- `src/Domain/Metis/Operation/ReadModel/*.php` and the Operation query handlers provide natural projection
  seams. `src/Application/AccessControl/Audit/Subscriber/AuditSubscriber.php` does not subscribe to Metis
  events; the all-events subscriber logs dispatched envelopes but is not authoritative operation history.
- `docs/wayfinding/00001-MAP.md` and `docs/epics/00035-EPIC.md` already bound the consumer-owned follow-up to
  Metis `Operation` and keep Chronos Platform Halt out of the first pilot.

### Fight CMS

- `src/Domain/ContentManagement/Post/Post.php` and its inherited `Node` behavior define draft, review,
  publication, removal, restoration, content, routing, category, tag, and comment state.
- `src/Application/ContentManagement/Post/CommandHandler/*.php` commits the ORM mutation before dispatching
  events through the synchronous dispatcher. `src/Application/Audit/Subscriber/AuditSubscriber.php` records
  the resulting lifecycle events as audit entries.
- `src/Domain/ContentManagement/Post/Event/*.php` provides a useful notification vocabulary, but those
  handler-created events were not designed as the aggregate's complete reconstruction history.
- `src/Adapter/Repository/ContentManagement/DoctrinePostRepository.php` and
  `database/schemas/ContentManagement.Post.Post.orm.xml` show that public and administrative reads share the
  mutable aggregate and its `Site`, `Category`, `Tag`, and `Link` relations; no aggregate version is mapped.
- A future greenfield Commerce bounded context is intentionally not evidence for the current CMS migration
  candidate. It is not yet specified and belongs to separate CMS-owned discovery if pursued.

### Fight Project Template

- `src/Domain/AccessControl/User/User.php` combines current identity and credential state, role relations,
  reset-token state, Doctrine collections, and Symfony security interfaces.
- `src/Application/AccessControl/User/CommandHandler/*.php` commits row changes before dispatching events.
  `src/Application/AccessControl/User/Subscriber/*.php` sends welcome and password-reset email, while
  `src/Application/AccessControl/Audit/Subscriber/AuditSubscriber.php` records the notifications.
- `src/Domain/AccessControl/User/Event/UserRegistered.php` does not contain the password hash, and
  `PasswordReset.php` does not contain the replacement hash. That is appropriate for notification events but
  proves the current events cannot reconstitute authoritative credential state.
- `src/Adapter/Repository/AccessControl/DoctrineUserRepository.php` and
  `database/schemas/AccessControl.User.User.orm.xml` establish the mutable row and role graph as the source of
  truth and show no aggregate version field.

## Recommendation

Use **Omphalos Metis `Operation`** as the single consumer migration pilot. It is useful because historical
governance and execution evidence is part of the capability rather than incidental telemetry. It is
representative because it exercises aggregate reconstitution, stable event mapping, expected-version
concurrency, legacy import, ordered projections, post-commit publication, worker operations, and recovery
around real external effects. It remains deliberately bounded to one aggregate and one bounded context.

Chronos `PlatformHalt` is the strongest second Omphalos candidate after the pilot. Its emergency file-based
override must remain independent of Event Store, database, projection, and worker availability. It is not
part of this pilot.

Fight CMS Commerce is a separate greenfield opportunity, not a second T-00001 pilot and not a migration
candidate. This evaluation makes no decisions about its domain model, routing, feature activation,
licensing, hosting, or commercial model.

## Staged Migration

1. **Close prerequisites in the owning repositories.** Finish the portable Fight Common aggregate, Event
   Store, repository, mapping, projection, and publication contracts. Complete the Omphalos Metis Wayfinder
   before creating implementation PRDs or tickets. Neither prerequisite blocks Fight Common 1.2 release.
2. **Define complete Metis facts.** Convert `Operation` to explicit aggregate-owned recording and replay.
   Add a complete request fact and a halt-refusal fact, audit every existing event payload for deterministic
   reconstitution, choose stable aliases and version-one schemas, and do not manufacture an upcaster without
   a real schema change.
3. **Deploy dormant infrastructure.** Add a deployment-independent `event_store` schema behind its own
   connection and credentials, the Metis event mapping provider, an event-sourced repository, a bounded
   projector worker, projection/checkpoint storage in the `app` database, lag and failure observability, and
   a manual rebuild operation. Keep legacy writes and reads authoritative at this stage.
4. **Import under a bounded write pause.** Pause new Metis requests, decisions, and executions. Represent
   each existing row with one explicit baseline/import event whose final name and payload are owned by the
   Omphalos design. Never fabricate historical transitions that were not recorded.
5. **Build and prove read state.** Project the imported streams into isolated candidate read state and compare
   counts, identifiers, lifecycle states, pending/ready queues, detail/outcome views, and representative
   filtered history against the legacy repository. Exercise a destructive manual rebuild before cutover.
6. **Cut over one write authority.** Switch Metis commands to the event-sourced repository and Metis reads to
   the proven projection, then resume writes. Do not run independent live dual writes across the `app` and
   `event_store` connections; the committed event stream becomes the sole write-side authority and the
   `app` state becomes a projection.
7. **Operate before retiring the legacy path.** Observe expected-version conflicts, projection lag, poison
   events, publication failures, and abandoned executions. Reconcile external effects by stable execution
   identity rather than replaying them blindly. Retire legacy mutation code only after the observation window
   and recovery drill pass.

## Rollback Boundary

Before step 6, rollback is direct: stop the dormant workers, discard candidate projections and imported test
streams as allowed by the consumer plan, and resume the untouched legacy row path. The write pause and parity
check make that decision explicit and reversible.

Step 6 is the authority boundary. After the first event-sourced write, the Event Store contains facts that the
legacy mutable path does not own, so silently switching row writes back on is unsafe. An operational rollback
must stop Metis writes and executions, preserve the Event Store, project every committed event, verify current
state, and deploy a compatible reader or perform a separately approved state-export migration. External
effects are reconciled by execution identity; neither aggregate replay nor publication replay is allowed to
repeat them implicitly.

## Operational Risks

- Existing notification events have payload and timestamp differences from aggregate state and are not yet a
  complete replay contract.
- Separate `app` and `event_store` connections make atomic dual writes unavailable; the cutover must establish
  one authority rather than rely on cross-schema transactions.
- Expected-version failures will expose concurrent human or agent decisions and require clear conflict
  handling at command boundaries.
- Eventually updated projections can change read-after-write behavior for approval and execution surfaces;
  lag must be observable and bounded.
- A crash after an external handler acts but before the final outcome append can leave an abandoned execution.
  Stable execution identity, detection, reconciliation, and safe retry are required.
- Published stored events are at least once around crashes. Subscriber side effects must be idempotent or own
  explicit deduplication; publication is separate from projection.
- Unknown aliases, incomplete upcast chains, poison events, checkpoint failures, and worker downtime fail
  closed and require operator-visible recovery procedures.
- Operation payloads, policy snapshots, result summaries, and failure metadata may contain sensitive data.
  The consumer must define classification, minimization, access, retention, and diagnostic-message policy
  before immutable storage.
- Baseline imports preserve current truth but cannot recover unrecorded history. They must remain visibly
  distinguishable from native lifecycle events.

## Unknowns

- Fight Common's accepted contracts were planning artifacts at the evaluation snapshot; implementation and
  consumer integration behavior remain unverified until their owning tickets complete.
- Production-like Operation count, event rate, concurrent-decision frequency, stream length, projection-lag
  target, retention volume, and rebuild duration have not been measured.
- The complete Metis subscriber and external-effect inventory, worker supervision topology, and operational
  alert thresholds still require Omphalos-owned evidence.
- Existing Operation payloads and failure metadata have not been classified for secrets or regulated data.
- The exact abandoned-execution reconciliation contract is unresolved in the Metis Wayfinder.

These are documented unknowns, not evidence that the corresponding behavior or risk is absent.

## Explicit Exclusions

- Implementing or prototyping the pilot in any consumer repository.
- Migrating Chronos Platform Halt or weakening its emergency file override.
- Event sourcing another Omphalos bounded context or every aggregate in Metis.
- Migrating Fight CMS `Post`, the Fight project template `User`, or any production data.
- Planning Fight CMS Commerce beyond recording it as a separate greenfield opportunity.
- Live cross-connection dual writes, cross-schema joins, foreign keys, or transactions.
- Fabricating legacy transitions or artificial schema churn solely to demonstrate upcasting.
- Snapshots, sagas, automatic destructive projection rebuild, automatic external-effect replay, and a general
  replay administration product.
- Blocking Fight Common 1.2 on consumer implementation, adoption, unresolved operational measurements, or
  the future CMS opportunity.

## Outcome

Selected Omphalos Metis `Operation` as the one bounded, non-release-blocking migration pilot. The evaluation
provides the shared-contract comparison, migration sequence, rollback boundary, risks, unknowns, and
exclusions needed for Omphalos to continue its existing Wayfinder before separately owned implementation.
