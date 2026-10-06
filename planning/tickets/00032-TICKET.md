---
id: TICKET-00032
epic: EPIC-00008
title: Construct Safe Pagination Requests
status: ready-for-agent
---

# Construct Safe Pagination Requests

## Problem statement

Pagination currently accepts negative bounds, silently treats unknown ordering directions as ASC, and can overflow
integer offset arithmetic into a float-assignment TypeError. Consumers need dependable query bounds without a
minor upgrade silently changing the existing constructor's documented/observed behavior.

## Solution and boundaries

- Add a strict named factory validating positive resolved page and page-size values and supported ordering directions.
  Omitted/null bounds use existing defaults; explicit zero/negative bounds reject. ASC/DESC normalize case-insensitively;
  invalid bounds/directions and offset overflow raise DomainException. Exact factory spelling/signature follow during
  implementation within these approved requirements.
- Calculate offset using checked integer arithmetic. Reject unsupported bounds before accidental float conversion
  or assignment; valid page one has offset zero and the requested positive limit.
- Preserve supported ordering normalization and field associations. Reject invalid directions through the strict
  path rather than silently translating them to ASC.
- Keep the legacy constructor functional during the minor transition, including zero/default selection and
  unknown-direction-to-ASC behavior. Document the strict alternative and deprecate permissive construction as
  appropriate; incompatible enforcement/removal waits for the major and its deprecation window.
- Existing repository consumers remain operable without adoption. No cursor pagination, SQL identifier validation,
  field authorization, arbitrary page-size policy or query execution is introduced.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer constructs a valid strict pagination request | N/A; value construction | N/A; bounds for a consumer-owned query | N/A; no domain mutation | Return coherent page, size, offset, limit and normalized ordering. |
| Consumer supplies negative/zero strict bounds or invalid ordering | N/A; value construction | N/A; no query executed | N/A; no domain mutation | Reject without a partially valid request or silent coercion. |
| Consumer supplies bounds whose offset exceeds the integer range | N/A; value construction | N/A; no query executed | N/A; no domain mutation | Predictable validation failure, not accidental float assignment. |
| Existing consumer uses the legacy constructor | N/A; existing construction | N/A; existing query vocabulary | N/A; no domain mutation | Preserve existing supported behavior during migration. |

Construction has no I/O, database write or schema effect. Repository/query policy and execution remain consumers'
responsibility; a valid Pagination is not authorization to run a query or sort on any particular field.

## Validation and permissions

Validate numeric bounds, representable arithmetic and direction syntax at strict construction. Document the
throwable family and omitted/default behavior. Do not invent query permissions or globally restrict consumer
ordering fields. Avoid new runtime deprecation warnings unless separately classified as compatible; PHPDoc/docs
can describe migration without adding unexpected runtime effects.

## Dependencies and sequencing

No new cross-TICKET dependency. Compatibility classification and strict/legacy behavioral evidence precede any
change to existing repository composition. Incompatible constructor enforcement is a future major transition,
not a hidden extra effect of shipping the new factory.

## Acceptance and evidence

- Use code-first feature development for the additive strict factory, then meaningful acceptance/rejection tests.
  Retain executable legacy negative-bound/overflow reproductions as limitation evidence, not a claim that unchanged
  legacy construction was repaired. Do not manufacture a red result caused only by a missing new factory. John
  approved this clarification during TASK decomposition; any actual defect repair still requires a failing regression.
- Cover page one, ordinary pages, valid ordering, omitted values, zero/negative strict bounds, invalid direction,
  integer boundary success and overflow rejection. Test arithmetic rather than relying on a TypeError.
- Preserve representative legacy constructor behavior and existing repository integration, including the
  current default/ordering paths. Do not claim legacy unsafe inputs were fixed by adding an opt-in factory.
- Classify the new public factory and exceptions under [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  with behavior assessed under [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Document strict construction, failure/overflow behavior, legacy limitations and the minor-to-major migration.
  Each implementation TASK owns its complete `./bin/build` gate, exact owned-production statement coverage,
  relevant repository evidence and independent review.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00128](../tasks/00128-TASK.md) | Add Strict Pagination Construction | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved strict additive construction with the legacy constructor retained for the minor in
[EPIC-00008](../epics/00008-EPIC.md), and approved this requirement area in its nine-TICKET split. The initial
assessment demonstrated negative offsets/limits and overflow; no implementation acceptance is claimed.
At that requirement checkpoint, no TASK, source change, commit or delivery operation was authorized.
Child status is generated above; [planning conventions](../CONVENTIONS.md) govern parent completion.

2026-10-06: John selected this TICKET and approved one feature TASK,
[TASK-00128 — Add Strict Pagination Construction](../tasks/00128-TASK.md), the recommended default/null, ordering and
DomainException contracts, and the code-first/legacy-reproduction testing clarification recorded above. The TASK owns
factory construction, unchanged legacy behavior, existing repository consumption evidence, API/behavior classification,
documentation/migration and the full gate. It has no blockers, including no dependency on TASK-00127, and no execution
priority is assigned. The TICKET is decomposed, not implemented or done. No source change, commit or delivery is
authorized. TICKET-00033 through TICKET-00039 still need their own TASK decomposition.
