---
id: TICKET-00056
epic: EPIC-00009
title: Record and inspect typed activity execution facts
status: done
---

# Record and inspect typed activity execution facts

## Problem statement

Trusted consumer instrumentation can already record safe phase activity, but cannot preserve typed operation,
known timing, measured elapsed duration and call/process result together through the canonical recording and
inspection path. `PhaseActivity` currently contains kind, summary, model and measured usage only.
`Observation::forActivity()` requires unknown top-level outcome and null top-level timing, while public activity
inspection deliberately omits extensions. Summary prose or a private extension codec is not a shared typed contract.

Agent OS's accepted sanitized phase-activity work needs these generic facts without making telemetry authoritative,
copying observations into another ledger or moving application Workflow/accounting policy into Common. Its current
Harness observes tool names, tool-call identities, errors and monotonic elapsed time, but that presentation path
is not a qualified Common producer. This requirement closes the supplier representation gap, not consumer capture.

## Solution and boundaries

Extend the existing activity value with optional cohesive typed execution facts and carry them through the same
Command, persistence, Query and read-only MCP Tool path. Common remains the canonical owner of generic execution
observations. This is an additive requirement amendment to EPIC-00009; completed TICKET-00040 through TICKET-00043
retain their historical acceptance and are not reopened.

- Represent a bounded safe capability/name target, nullable known start/end and independently measured elapsed
  duration, and an explicit observed result. Preserve unknown target/result/timing rather than manufacturing facts.
- Distinguish a call returning, a call throwing, process exit and observed cancellation. None proves business commit,
  Workflow success, external delivery or Review acceptance. A cancellation request is not observed cancellation.
- Use immutable cohesive values, existing precision-preserving `ObservationOccurrence` and fixed elapsed `Duration`.
  Do not add arbitrary fields, inheritance-based variants or a generic options bag. Concrete APIs and invariants
  accepted for implementation are recorded in TASK-00205.
- Keep execution facts inside `PhaseActivity`. Do not unlock activity's top-level session/phase timing/outcome or
  allow a tool result to become the latest session/phase state observation. Keep performing Agent, instrumentation
  producer, model and activity target distinct.
- Retain the existing producer/full-UUID identity, current admission, immutable submitted evidence, canonical
  comparisons, retained retry/conflict behavior, server receipt time, transaction ownership and safe post-commit fact.
- Extend the existing safe activity projection and MCP output schema together. No blanket extension disclosure,
  additional Tool, protected body channel or duplicate application observation ledger is introduced.
- Amend the never-released current version-one representation directly under the project compatibility rule.
  Preserve tagged APIs and actually needed durable data; no automatic migration, disposal or historical snapshot
  reader is authorized.

Optional capture and application-derived state may commit independently: committed event → application policy
subscriber → configured CommandBus → secondary Command. Missing/delayed capture or projection is acceptable when
incompleteness and stable source identity remain explicit. Do not expand transaction APIs merely to synchronize
optional observations. Required audit, safety restrictions and required durable intent retain their owning
operation's guarantees; subscribers are not substitutes for those participants.

Common's existing measured usage, captured-detail references and live pagination remain unchanged. Consumers own
Workflow/process/request attribution, stable history projections, normalized/corrected accounting, richer timing
categories and Artifact reference/hold policy. Common Tools remain concrete, final and permission-neutral;
consumers bind permissions through qualified Access Control composition, not subclasses or copied Tools.

## Use cases

Actors are trusted instrumentation and currently authorized human/Agent inspection callers. Source execution,
provider work and consumer UI are not invoked by recording or inspection.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Record known tool/process facts or explicit missing facts | Existing `RecordPhaseObservation` | Existing internal relationship/retry checks | Existing safe `ObservationRecorded` after commit | Atomically retain typed execution facts with the activity, never business state |
| Retry identical or conflicting evidence | Same recording Command with the same producer/UUID | Existing retained comparison | Identical retries do not redispatch | Preserve receipt and one retained record; changed execution facts conflict |
| Inspect authorized activity | N/A — read-only | Existing `ListPhaseActivity`, also through `execution.activity` | None | Return bounded safe typed execution facts without bodies or read-side mutation |
| Fail storage, dispatch or remove protected detail | Existing recording/removal operations | Existing safe/protected Queries as admitted | Existing recording fact only; no new removal event | Preserve rollback/uncertainty distinctions, body-removal/no-resurrection and truthful availability |

## Validation and permissions

Existing recording admission supplies current trusted producer and exact project/session/phase scope before retained
lookups. Existing execution-read admission and separate detail-reference/body grants still govern inspection.
New values carry no permission, actor credentials, Workflow authority or evidence-read grant.

Validate closed structured representations, finite identities and safe classification names, timing precision and
result-specific combinations. Reject negative elapsed duration, incompatible supplied endpoints, unsupported forms
and exceeded bounds without raw values in public errors. Do not infer monotonic elapsed from wall endpoints or
require independent clocks to agree. Consumer sanitization precedes persistence; structural validation does not
prove that a valid string is secret-free. No arguments, shell commands, paths, environment, raw exceptions, provider
bodies, prompts, transcripts or hidden reasoning belong in these safe execution facts.

Protected-body removal does not redact safe activity metadata or application copies. Retained safe identity prevents
old capture retries from restoring removed bodies; deliberate identity purge ends deduplication. Consumers must keep
affected disclosure denied and unsupported cleanup claims blocked until actual lifecycle participants are qualified.
This requirement adds no safe-metadata purge/redaction engine, attributed removal tombstones or permanent retry ledger.

## Acceptance and evidence

- Typed facts survive the existing real Command/persistence/reconstruction path and authorized Query/Tool inspection,
  including partial endpoints, unknown values, zero measured elapsed and the distinct observed result kinds.
- Identical retries retain their original receipt with no event replay; changed target/timing/result conflicts.
  Rollback, caller-transaction rejection, uncertain commit and post-commit dispatch failure keep their current meaning.
- Rich activity results do not change latest session/phase outcomes, usage arithmetic, detail grants or body-removal
  guarantees. No protected/extension/private diagnostic data appears in safe output.
- Default and configured execution tables work on SQLite, MySQL and PostgreSQL. Independently expected public facts
  qualify the guarded HTTP Tool path; Query-to-Tool equality alone is insufficient evidence.
- Document the implemented public values, representation/bounds, failure semantics and caller examples; align affected
  README, CHANGELOG, execution guides and CONTEXT.md without claiming capture, consumer adoption or a tagged release.
- TASK-owned focused checks, exact owned-production statement coverage, complete `./bin/build`, independent Review
  acceptance and applicable QA precede completion. Package release certification remains separately authorized.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00205](../tasks/00205-TASK.md) | Record and inspect typed activity execution facts | done |
<!-- /planning:children -->

## Decisions and progress

On 2026-11-06 John approved the bounded pre-v1.5 architecture proposal and requested additional task planning in an
isolated worktree. The approved split is one complete recording-to-inspection TASK, not separate Domain, persistence,
Query and Tool PRs: [TASK-00205 — Record and inspect typed activity execution facts](../tasks/00205-TASK.md).

The assessment inspected Common `60d66b30aa6ff75686abc1435c5f8cb8a6b10651` and clean Agent OS
`3721164c09c84564a5f611db5a3ff18270917284`. The supplied 6 November observability PDF is a historical audit;
subsequent maintainer decisions above govern. Agent OS still locks Common v1.3.0 and Access Control v0.8.0 at that
inspection. Neither source inspection nor this approval qualifies installed v1.5 integration.

TASK-00201 and TASK-00202 retain their existing scopes and ordering; neither supplies or blocks these execution
facts. Completed configured execution recording/inspection suppliers are reused. Consumer plan reconciliation,
real producer integration, stable browsing/accounting/lifecycle work and Access Control external-Tool binding remain
with their existing repositories/owners, not additional Common tasks. No new global priority or tag permission is
assigned. All decomposition for this amendment is written; the original closed Wayfinder decisions stay closed.

The preceding planning checkpoint records approval before implementation; its no-build/publication claims are
historical. TASK-00205 now records independent Review ACCEPT and required QA PASS for candidate
`eaefce6681a6f74da0d4afb4aa7d0e6050f46a6a`, followed by behavior-preserving develop integration and a fresh
complete local gate (10,173 tests / 103,384 assertions; exact 19,259/19,259 statements). Its sole child is accepted
and complete, so this TICKET closes under automatic parent completion. The owning TASK and durable Land receipt
retain acceptance, integration, publication and cleanup separately. No release or consumer adoption is claimed.
