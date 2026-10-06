---
id: TICKET-00031
epic: EPIC-00008
title: Safely Reuse Validation Services
status: ready-for-agent
---

# Safely Reuse Validation Services

## Problem statement

A validator exception currently bypasses coordinator cleanup. A consumer reusing the same ValidationService can
then run stale validators against unrelated input and receive errors belonging to the failed operation. Validation
must be isolated per invocation without concealing the original failure or replacing the existing service API.

## Solution and boundaries

- Ensure validator/rule state is cleared on normal completion and exceptional exits.
- Preserve ordinary valid/invalid result behavior and the original throwable's propagation and causal information.
- After failure, an invocation with different input/rules, including no rules, behaves like a fresh invocation.
- Keep cleanup with the owning validation coordinator/service, not with every consumer or HTTP adapter.
- Do not add rules, replace framework validation, change business authorization, or introduce concurrency or
  reentrancy guarantees not already promised by the capability.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer validates ordinary input repeatedly | N/A; existing service call | N/A; no CQRS read | N/A; no domain mutation | Return each invocation's own data/errors and clear invocation state. |
| A consumer-supplied validator throws | N/A; existing service call | N/A; no CQRS read | N/A; no domain mutation | Propagate the original failure; clear stale validator/rule state before the next call. |
| Consumer retries or validates unrelated input after failure | N/A; existing service call | N/A; no CQRS read | N/A; no domain mutation | Run only the new invocation's rules; no stale errors or validators. |

These are local validation operations, not new business messages. User-supplied validators retain responsibility
for their own side effects; cleanup is not a rollback mechanism. No persistence/schema changes are required.

## Validation and permissions

Preserve existing rule and input validation, including structured validation failures. Distinguish an ordinary
invalid result from an unexpected validator throwable; do not convert the latter into success or silently absorb
it. Authorization is N/A to this repair and remains consumer-owned. Do not widen the coordinator's responsibility
to sanitizing HTTP responses; [TICKET-00039](00039-TICKET.md) owns that separate boundary.

## Dependencies and sequencing

Use the existing ValidationService/ValidationCoordinator contract. This TICKET has no new cross-TICKET prerequisite
and is independently useful. It does not require new error categories from [TICKET-00038](00038-TICKET.md).

## Acceptance and evidence

- Start with a failing regression reproducing exception-state leakage on a reused service/coordinator.
- Prove reuse after a throwable with unrelated input and empty rules; stale validators must not run.
- Cover normal success, ordinary validation failure, exceptional failure and a subsequent successful invocation.
  Assert original failure propagation and that cleanup does not replace it.
- Retain the existing public validation exception family, structured data and successful invocation behavior.
  Record the behavioral compatibility assessment rather than relying on unchanged signatures.
- Update affected validation documentation and CONTEXT.md if the shipped contract description changes. Each
  implementation TASK supplies focused evidence and the complete `./bin/build` gate with exact owned-production
  statement coverage, then independent review; verification is not deferred to another TICKET.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00127](../tasks/00127-TASK.md) | Make Validation Reuse Exception-Safe | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved this requirement area in [EPIC-00008](../epics/00008-EPIC.md)'s nine-TICKET split.
The initial assessment reproduced stale validation after a validator threw; no repair is implemented here.
[ADR 0010](../adr/0010-behavioral-contract-authority.md) and
[ADR 0011](../adr/0011-non-structural-compatibility-policy.md) govern behavior and exceptions.
At that requirement checkpoint, no implementation, commit or publication was authorized. Child status is generated
above and parent completion follows [planning conventions](../CONVENTIONS.md).

2026-10-06: John explicitly selected this TICKET and approved a single bug TASK,
[TASK-00127 — Make Validation Reuse Exception-Safe](../tasks/00127-TASK.md). It owns coordinator/service cleanup,
including thrown validators and service preparation failures with queued validators, preserved original failures
and normal results, regression-first evidence, compatibility/documentation and the full gate. No TASK blockers or
execution priority are assigned. The TICKET is decomposed, not implemented or done; no source repair, commit or
publication is authorized. The other eight EPIC TICKETs still need their own TASK decomposition.
