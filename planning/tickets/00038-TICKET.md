---
id: TICKET-00038
epic: EPIC-00008
title: Express Transport-Neutral Application Failures
status: ready-for-agent
---

# Express Transport-Neutral Application Failures

## Problem statement

Command/query handlers need reusable failure categories that consumers and adapters can recognize without
throwing HTTP-framework exceptions or inferring business meaning from arbitrary diagnostic strings. Today,
consumers recreate this vocabulary and risk treating internal failures as safe client-facing messages.

## Solution and boundaries

- Add a reusable exception vocabulary under `Application/Error/Exception`. Categories describe application
  outcomes, not HTTP statuses, JSend bodies, headers or a framework response.
- Cover AuthenticationRequired, PermissionDenied, ResourceNotFound, StateConflict, ResourceGone, PreconditionFailed,
  InvalidInput, RuleViolation, RateLimitExceeded, ServerError and ServiceUnavailable. Use abstract ApplicationException
  extending Common's SystemException, retaining Catchable and deliberately supporting consumer Application subtypes
  of the concrete categories. Domain must not import these Application categories.
- Preserve existing Application/Domain validation constructors, fromErrors(), getErrors(), causes, codes and
  catchability/subtype behavior without reparenting, duplication or mandatory replacement. InvalidInput expresses
  unusable input without requiring field errors; existing ValidationException owns structured field validation;
  RuleViolation expresses deliberate business-rule rejection. Classify extension promises deliberately.
- Ordinary exception messages, codes, previous exceptions and contextual data remain diagnostic, not public-safe
  merely because the type is recognized. Retain arbitrary supported integer codes and previous Throwable identity,
  including PHP Error causes, without requiring HTTP codes or adding a public-message/diagnostic-serialization API.
- RateLimitExceeded and ServiceUnavailable support optional neutral retry-delay seconds: native integer >=0, null
  meaning unspecified. Zero is a valid hint, not retry permission. Validate supported metadata; no clock default,
  automatic scheduling, Duration dependency or HTTP header/public projection is introduced.
- Permit handlers/consumers to express failure intent directly. Domain policy remains with Domain; reusable generic
  categories do not replace domain-specific decisions, authorization enforcement or consumer-owned error intent.
- Do not automatically classify generic LookupException, storage/auth infrastructure exceptions or every timeout
  as a public missing-resource/authentication/gateway failure. Diagnostic equivalence is not semantic equivalence.
- Exclude one exception per HTTP status, framework routing/content-negotiation failure types, new permission policy,
  authentication mechanisms, Domain-to-Application dependencies and a generic application-policy engine.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer-owned command handler refuses a classified operation | Existing consumer command; no Common command added | N/A; no new query | N/A; refusal does not invent an event | Throw a transport-neutral category while retaining consumer-owned policy and failure/rollback semantics. |
| Consumer-owned query handler reports an intentionally missing resource | N/A; no mutation | Existing consumer query; no Common query added | N/A; no domain mutation | Throw ResourceNotFound intent without importing an HTTP exception. |
| Console, worker or HTTP adapter recognizes a failure category | N/A; no business command | N/A; local classification | N/A; no domain mutation | The adapter chooses its own presentation/retry/transport behavior. |
| Consumer supplies diagnostic context or a previous exception | N/A; exception construction | N/A; local access | N/A; no domain mutation | Retain supported diagnostic information without making it automatically public. |
| Existing consumer uses published validation/domain exceptions | Existing workflow unchanged | Existing workflow unchanged | Existing workflow unchanged | No mandatory replacement or changed catchability. |

No new business messages, persistence/schema changes, network calls, automatic retry or rollback mechanism are
introduced. Throwing a category does not authorize an action or imply that a command's earlier effects rolled back.
Consumers remain responsible for transactions and recovery.

## Validation and permissions

Validate any category-specific structured metadata before accepting it. Exact metadata design must be justified by
use cases rather than arbitrary bags of HTTP headers. PermissionDenied and AuthenticationRequired report explicit
consumer decisions; they perform no checks themselves. Preserve domain-specific failures instead of moving domain
policy inward/outward just to fit a generic category. Public-message projection and logging belong to adapters.

## Dependencies and sequencing

No new prerequisite among the other EPIC TICKETs. Reuse existing exception/validation conventions and classify
new public promises under [ADR 0009](../adr/0009-public-api-manifest-baseline.md).
[TICKET-00039](00039-TICKET.md) depends on this vocabulary and owns safe HTTP translation. Other transports may
consume the same categories without any dependency on that HTTP implementation.

The approved complete slice is [TASK-00142 — Express Transport-Neutral Application Failures](../tasks/00142-TASK.md),
a code-first additive feature with no TASK blockers or execution priority. Existing validation cleanup/TASK-00127 is
not a prerequisite. The vocabulary is one independently usable capability, not separate layer/exception-class PRs;
it owns validation reconciliation, meaningful tests, docs/CONTEXT.md, compatibility, full gate and independent review.
Constructor/accessor/factory spellings remain routine within the approved semantics. TICKET-00039 is now decomposed
into [TASK-00143](../tasks/00143-TASK.md), which directly depends on TASK-00142, and independent Symfony/Laravel
integrations TASK-00144/TASK-00145 blocked by TASK-00143. Safe HTTP proof is not claimed from new type existence.

## Acceptance and evidence

- Prove construction, category recognition, intended catchability, supported causal/diagnostic information and
  representative command/query-handler usage without HTTP/framework imports.
- Prove existing validation exception behavior remains available and any category overlap has one documented
  responsibility, not competing generic/public interpretations.
- Exercise deliberately sensitive synthetic diagnostic messages and causes to establish that the contract does
  not mark getMessage or arbitrary exception codes as safe public data. HTTP non-disclosure is separately proved
  by TICKET-00039, not claimed from type existence alone.
- Document each category's meaning, illustrative handler usage, transport independence, diagnostic/public
  separation and consumer policy/transaction ownership. Avoid a blanket mapping for every generic lookup or
  infrastructure exception.
- Classify new declarations, extensibility, exception families and observable promises under ADR 0009,
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md). Additive vocabulary targets the minor; it does not
  authorize incompatible changes to existing catchability or runtime deprecation effects.
- Each implementation TASK owns focused behavior evidence, the complete `./bin/build` gate, exact owned-production
  statement coverage and independent review. No downstream Agent OS source changes or release are included.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00142](../tasks/00142-TASK.md) | Express Transport-Neutral Application Failures | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved adding transport-neutral application failures and safe HTTP presentation to
[EPIC-00008](../epics/00008-EPIC.md), extending the proposed split to nine TICKETs. Agent OS's Application/Failure
exceptions are reference evidence, not an authoritative Common hierarchy or a mandatory consumer migration.
At that requirement checkpoint names were illustrative and this TICKET was ready for decomposition only, with no
new exception type/source change/publication implemented. Child status is generated above and parent completion
follows [planning conventions](../CONVENTIONS.md).

2026-10-06: John selected this TICKET and approved one complete feature TASK,
[TASK-00142 — Express Transport-Neutral Application Failures](../tasks/00142-TASK.md), and its recommended contracts:
all eleven named categories, abstract ApplicationException/SystemException/Catchable ancestry, deliberate consumer
Application extension, diagnostic message/integer-code/cause preservation, unchanged structured-validation contracts
and optional nonnegative integer retry-delay hints for RateLimitExceeded/ServiceUnavailable. No TASK blocker,
execution priority, automatic policy/mapping, transport fields/public-message API or mandatory migration is assigned.

Current source inspection confirms validation inheritance, late-static fromErrors construction and published
extensibility; AuthException already derives from SystemException. Routing buses directly invoke their handlers,
and MCP mapping remains exact-class consumer-authored safe constants. These are source/test/manifest observations,
not new category product tests, fresh full gate/coverage, HTTP non-disclosure or consumer runtime/security evidence.
Existing HTTP diagnostics remain unsafe in legacy paths; TICKET-00039 owns opt-in safe handling separately.

This TICKET is decomposed, not done. All implementation criteria, product verification and independent review remain
pending. At that checkpoint TICKET-00039 still needed decomposition; no new type/source change, implementation branch, worktree, commit,
publication, merge, certification, signing, release, deployment or rollback/security improvement is implemented,
authorized or claimed by planning.

At that checkpoint planning-only verification passed: `./bin/planning-check --write` and `./bin/planning-check` reported 182 records /
26 active, generated views refreshed/current, and tracked/new-record whitespace checks passed. TASK-00142 is on
Ready Frontier without blockers/order; TICKET-00039 is the remaining decomposition frontier. No fresh product
full gate/coverage or HTTP non-disclosure result is claimed by these planning checks.
