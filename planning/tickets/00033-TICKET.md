---
id: TICKET-00033
epic: EPIC-00008
title: Snapshot JSON Without Mutable Aliasing
status: ready-for-agent
---

# Snapshot JSON Without Mutable Aliasing

## Problem statement

JsonObject currently retains supplied objects and references. Later mutation can change its JSON, equality and
hash, making it unsafe where consumers expect an immutable value. Consumers need explicit snapshot construction
without losing the existing mixed-data API or replacing the independently supported StrictJson capability.

## Solution and boundaries

- Add opt-in snapshot construction, provisionally `JsonObject::fromSnapshot()`, for JSON-encodable mixed input.
  Scalars, arrays, ordinary objects and supported JsonSerializable values are not narrowed to arrays alone.
- Add snapshot-aware text reconstruction, provisionally `fromSnapshotString()`. Do not reuse/change legacy fromString
  to promise faithful snapshot restoration: its associative decoding and encoding collapse empty/numeric-key objects
  and zero-fraction float representations. Existing Doctrine hydration remains legacy, not automatic snapshot adoption.
- Encode the input at construction to detach nested mutable objects and references. Custom JsonSerializable
  objects contribute their JSON output, not their PHP class identity or a retained executable object graph.
- Independently reconstruct every mutable output exposed through data/JSON representations. Caller mutation of
  the original input or a previously returned output cannot change snapshot JSON, equality or hash.
- Preserve distinctions between JSON objects, lists, scalars and null, native integers and finite floats, including
  `1` versus `1.0`. Establish/document native precision, including serialize_precision, without promising arbitrary-
  precision decimals. Text import rejects integer literals outside the native range rather than converting to floats
  or strings. Use object-mode reconstruction and codec depth 512; reject anything that cannot independently decode
  within that limit. Preserve zero-fraction floats and allow presentation-only encoding controls, rejecting coercive
  or error-substitution flags. Verify Unicode, numeric/depth boundaries and the complete supported option set.
- Keep existing `fromData(mixed)` and other legacy paths unchanged in the minor. Preserve StrictJson and all MCP
  contracts; snapshots are a JsonObject capability, not a replacement or silent conversion of existing values.
- Do not promise deep cloning of arbitrary PHP class identity, object methods, resources or non-JSON state.
  General JSON canonicalization or semantic object-key-order equivalence is not introduced by implication.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer snapshots mixed JSON-encodable data | N/A; value construction | N/A; local encoding | N/A; no domain mutation | Detach the encoded snapshot, including nested objects/references. |
| Caller mutates input or a returned data/JSON representation | N/A; caller-owned mutation | N/A; local value access | N/A; no domain mutation | Snapshot JSON, equality and hash remain unchanged; later output is independent. |
| Consumer supplies a custom JsonSerializable object | N/A; value construction | N/A; local serialization | N/A; no domain mutation | Capture its JSON output without retaining the original class instance. |
| Consumer reconstructs snapshot JSON text | N/A; value reconstruction | N/A; local decoding | N/A; no domain mutation | Restore supported object/list/scalar/null and numeric kinds through the opt-in text factory, not legacy hydration. |
| Consumer supplies invalid/unencodable data | N/A; value construction | N/A; no external read | N/A; no domain mutation | Reject predictably; do not produce a partial or silently substituted snapshot. |
| Existing consumer uses fromData or StrictJson | N/A; existing value API | N/A; local representation | N/A; no domain mutation | Retain the existing supported contracts without mandatory migration. |

Encoding invokes any supplied JsonSerializable behavior at the snapshot boundary. Its caller-defined effects are
not rolled back or sandboxed by Common. Snapshot storage/access itself requires no network or persistence changes.

## Validation and permissions

Validate snapshot construction and every supported encoding/decoding boundary. Codec/representation failures use
fixed-message DomainException with underlying causes where available; consumer serializer throwables propagate
unchanged. Do not pretend resources/cycles/unrepresentable values can be faithfully preserved. Authorization is
N/A to a JSON value; consumers decide which input and safe presentation data they serialize. Snapshot immutability
does not redact sensitive content or make it suitable for an HTTP response.

## Dependencies and sequencing

No new cross-TICKET prerequisite. Reuse existing value conventions and preserve existing StrictJson/MCP tests and
contracts. Resolve encoding/precision representation choices before committing a public snapshot format.

## Acceptance and evidence

- Use code-first development for the additive snapshot feature, then meaningful isolation/reconstruction tests.
  Retain executable legacy-aliasing reproduction evidence and prove legacy construction is not silently changed.
  John approved this clarification during TASK decomposition: do not manufacture a red result caused only by a missing
  factory. Any actual legacy defect repair still requires a meaningful failing regression before repair.
- Cover input and output isolation for nested objects, references, arrays, repeated access and custom
  JsonSerializable output. Mutation through either data access or jsonSerialize must not alter the value.
- Cover object/list/scalar/null distinction, equality/hash stability and supported string/JSON round trips,
  numeric boundaries, invalid encoding and representation failures.
- Classify additive declarations and observable behavior under [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Document snapshot versus legacy semantics, supported JSON precision/options, custom serialization and limitations.
  Each implementation TASK owns the full `./bin/build` gate, exact owned-production coverage, relevant preserved
  StrictJson/MCP evidence and independent review; no packaging/release tests are added.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00129](../tasks/00129-TASK.md) | Add Immutable JSON Snapshots | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved opt-in immutable JsonObject snapshots and preservation of mixed-data/StrictJson contracts
in [EPIC-00008](../epics/00008-EPIC.md), then approved this requirement area in the nine-TICKET split.
At that requirement checkpoint, factory spelling and encoding controls remained routine design within those
boundaries, not authority to weaken immutability or introduce a legacy break; only TASK decomposition was ready.
Child status is generated above and parent completion follows [planning conventions](../CONVENTIONS.md).

2026-10-06: John selected this TICKET and approved one feature TASK,
[TASK-00129 — Add Immutable JSON Snapshots](../tasks/00129-TASK.md), the representation/failure contracts above,
additive snapshot-aware text reconstruction and the code-first/legacy-reproduction testing clarification. Exact
factory spelling/signatures and the presentation-option allowlist remain implementation design within these approved
requirements. The TASK owns capture, fresh outputs, reconstruction, precision/failure boundaries, legacy/Doctrine/
StrictJson/MCP compatibility, documentation and the full gate. It has no blockers or dependency on TASK-00127 or
TASK-00128, and no execution priority is assigned. The TICKET is decomposed, not implemented or done; no source
change, commit, publication or delivery is authorized. TICKET-00034 through TICKET-00039 still need TASK decomposition.
