---
id: TICKET-00034
epic: EPIC-00008
title: Treat Stream Identifiers as Values
status: ready-for-agent
---

# Treat Stream Identifiers as Values

## Problem statement

StreamId describes a stable aggregate-name/identifier tuple but does not implement the existing Value/Identifier
contracts. Equivalent tuples can behave as different objects in value collections, and adapters repeat tuple
comparison knowledge. Consumers need coherent reusable identity without rewriting persisted stream identifiers.

## Solution and boundaries

- Make StreamId own tuple equality, hash, comparison and reconstruction through the existing Identifier contract.
  Use ValueObject conventions where suitable; do not introduce a UUID-specific UniqueId requirement.
- Preserve aggregate-name/identifier access and the existing nonempty-component invariants. Equal tuples have
  equal hashes and equivalent ordering; different tuples remain distinguishable.
- Use a stable, unambiguous versioned ASCII representation that preserves component bytes exactly, for example
  `stream:v1:<base64(name)>:<base64(id)>`. No trimming, case normalization, numeric coercion or new Unicode restriction.
  JSON exposes the canonical representation. Equality with a wrong type returns false; malformed reconstruction
  and wrong-type comparison raise DomainException. Compare aggregate name, then identifier, bytewise.
- Preserve persisted aggregate-name/identifier fields, adapter lookup meaning and repository identity contracts.
  In-memory comparison may delegate to the owned value only after the compatibility effect is established.
- Target direct StreamId promotion at the following major, explicitly outside this EPIC's next-minor delivery.
  John approved this conservative boundary after collection/native-JSON probes; no parallel opt-in value type or
  minor compatibility exception is selected. Assess public/behavioral/serialization effects and migration guidance
  honestly; this planning decision is not an exact-version approval or completed release certification.
- Exclude storage layouts/migrations, event-store concurrency, mapping/upcasting, new aggregate ID classes,
  publication/projection changes and general identity-framework reorganization.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer compares separately constructed equal stream tuples | N/A; value operation | N/A; local comparison | N/A; no domain mutation | Coherent equality, comparison and equal hashes. |
| Consumer reconstructs a stream identifier containing separators | N/A; value construction | N/A; local decoding | N/A; no domain mutation | Recover both components exactly; distinct tuples cannot collide. |
| Consumer inserts equal tuples into a value/sorted collection | N/A; caller-owned collection update | N/A; local collection access | N/A; no domain mutation | Intended value semantics with explicitly classified migration impact. |
| Repository/store looks up an existing stream | N/A; existing capability unchanged | Existing store/repository read; no new query type | N/A; no new domain event | Same persisted stream is selected; no rewritten identity data. |

This changes local identity behavior, not persistence transaction guarantees. No database/schema rewrite or new
business messages are required. A stream identifier never proves access permission.

## Validation and permissions

Preserve component validation, reject malformed representations predictably, and document comparison type/failure
behavior. Authorization and stream access policy remain consumer-owned. Do not reinterpret lookup failures as
HTTP ResourceNotFound here; application failures and presentation belong to their separate owners.

## Dependencies and sequencing

Reuse the existing Identifier/Value/collection capabilities and
[ADR 0001](../adr/0001-event-sourcing-contract.md)'s stable stream identity contract. No new cross-TICKET prerequisite.
Compatibility classification precedes adapter/collection adoption; source-compatible interface additions alone
are not proof that a minor release can change existing collection uniqueness. There are no TASK dependencies, but
execution/integration must respect the approved following-major target. Verify actual branch/timing suitability at
work intake rather than merging the promotion into a next-minor line or inventing a branch-policy exception.

## Acceptance and evidence

- Retain current object-identity collection and native-JSON reproduction evidence; use code-first feature
  development and meaningful value/collection regressions for the selected following-major delivery. Do not silently
  rewrite a legacy expectation to claim minor compatibility or manufacture a red result caused only by a missing API.
  Any actual separate defect repair remains regression-first.
- Cover equal/different tuples, equal hashes, comparison consistency, malformed input and round trips with
  ambiguous delimiter characters. Assert unchanged component access and persisted identity fields.
- Exercise relevant typed and sorted collections, in-memory stream lookup and existing durable-store identity
  behavior without adding unrelated database-concurrency qualification requirements.
- Classify declarations and semantic behavior under [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md). Escalate material compatibility uncertainty.
- Document value semantics, stable representation and any major-transition/deprecation guidance. Each implementation
  TASK owns the complete `./bin/build` gate, exact owned-production coverage and independent review.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00130](../tasks/00130-TASK.md) | Give StreamId Tuple Value Semantics | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved this requirement area in [EPIC-00008](../epics/00008-EPIC.md)'s nine-TICKET split.
At that requirement checkpoint, final grammar and compatible delivery mechanics remained for TASK design;
only the value destination was accepted, not destructive persisted-data changes or an incompatible minor release.
Child status is generated above; [planning conventions](../CONVENTIONS.md) govern parent completion.

2026-10-06: John selected this TICKET and approved one feature TASK,
[TASK-00130 — Give StreamId Tuple Value Semantics](../tasks/00130-TASK.md), its recommended contracts and moving direct
promotion out of next-minor delivery into the following major. The baseline probe showed equal tuples occupying two
HashSet entries, a third equivalent tuple unable to find/remove them, different FastHasher values and native JSON
`{}`; nonempty control/binary component strings remain accepted. Identifier promotion activates collection dispatch
and changes JSON representation, so unchanged constructor signatures are not sufficient compatibility evidence.

The TASK owns tuple value behavior, canonical reconstruction, in-memory delegation, typed/hash/sorted collection and
preserved durable-store identity evidence, compatibility/migration docs and the full gate. It has no TASK dependencies
or execution priority. Exact grammar details remain routine implementation design within byte-preserving ASCII
framing; no major-target branch is allocated. This is a conservative delivery boundary, not an exact release decision,
completed certification, minor waiver, implementation or publication authority. The TICKET is decomposed, not done;
TICKET-00035 through TICKET-00039 still need TASK decomposition.
