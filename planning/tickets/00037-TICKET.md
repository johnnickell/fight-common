---
id: TICKET-00037
epic: EPIC-00008
title: Calculate Exact Monetary Values
status: ready-for-agent
---

# Calculate Exact Monetary Values

## Problem statement

Consumers need reusable exact decimal and monetary calculations without floating-point approximation, guessed
currency precision or historical amounts being reinterpreted by catalog updates. Novuso supplies useful reference
interfaces but its historical table and numeric calculations are not the new Fight contract.

## Solution and boundaries

- Add exact string-based Decimal with whole-string signed plain ASCII decimal syntax, no exponent/separators/
  whitespace/float factories and normalized numeric identity (1, 1.0 and +01.000 equal; negative zero becomes zero).
  Bounds are 1,024 normalized coefficient digits, normalized/requested scale 0–1,024 and 4 KiB input. Exact arithmetic
  and finite division reject unsupported results; scaled/rounded operations require explicit native PHP RoundingMode.
  Use a plain-PHP baseline without required BCMath/GMP/third-party Domain arithmetic or binary-float calculations.
- Add recognized ISO Currency with a versioned offline catalog, source publication/provenance and distinct
  current/historical status. Construction performs no network lookup. Alphabetic code is currency identity;
  numeric-code reuse or display-name changes do not collapse distinct currencies. Preserve numeric metadata as
  three-character strings including leading zeros; catalog revision/status/name is not code identity. Presence in
  historical rows alone does not make a code globally withdrawn if the current catalog also contains it.
- Include historical recognition without requiring that consumers permit historical currencies for new transactions.
  Establish supported historic precision from evidence; List Three alone does not provide all required metadata.
- Keep ISO accounting fraction digits separate from physical subdivisions and consumer cash-rounding policy.
  Currency can represent unavailable precision. Minor-unit Money construction rejects it until a supported
  precision definition exists; never substitute two digits for ISO N.A. or guess historical precision.
- Add signed native-integer minor-unit Money, including PHP_INT_MIN without a new 64-bit floor, and checked
  compatible-currency/scale arithmetic. Capture supported accounting scale/definition; exact conversion/scalar
  calculation rejects fractional minor results unless explicit rounding is selected, with one monetary-boundary
  rounding decision. Identity reflects code, captured scale and amount, not display metadata.
- Allocate a nonempty list of nonnegative integer weights with at least one positive weight using largest remainder,
  ties by original index. Compute magnitude/intermediates exactly, restore sign and original order, give zero weights
  zero shares and preserve positive/zero/negative totals, including native minimum. Reject unsupported inputs.
- Catalog updates must not reinterpret saved scale, replace codes or silently redenominate. Versioned reconstruction
  retains exact textual minor units, code/scale and supported definition context; retained reader definitions preserve
  old meaning rather than replacing it with a current default. Unsupported/malformed input rejects predictably.
- Decimal, Currency and Money are new Fight APIs. No Novuso constructor, metadata-field, calculation or serialization
  compatibility is required. Verify code/data reuse terms before adapting or redistributing external material.
- Exclude exchange rates, automatic currency conversion, localized formatting, arbitrary application financial policy,
  automatic successor-currency replacement and network-dependent construction.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer parses, calculates or rounds an exact decimal | N/A; value operation | N/A; local calculation | N/A; no domain mutation | Exact result or explicit rounding/rejection, without float approximation. |
| Consumer identifies a current or historical currency | N/A; value construction | N/A; local catalog lookup | N/A; no domain mutation | Stable code identity, supported provenance/status and explicit precision availability. |
| Consumer constructs or calculates signed minor-unit Money | N/A; value operation | N/A; local calculation | N/A; no domain mutation | Correct same-currency result with checked representability. |
| Consumer supplies mismatched currency, overflow or unavailable precision | N/A; value operation | N/A; local checks | N/A; no domain mutation | Predictable rejection instead of coercion, guessed scale or automatic conversion. |
| Consumer allocates positive, zero or negative Money | N/A; value operation | N/A; local calculation | N/A; no domain mutation | Deterministic allocations whose exact sum equals the original amount. |
| Consumer reconstructs a saved amount after metadata maintenance | N/A; value reconstruction | N/A; local decoding | N/A; no domain mutation | Original currency and monetary meaning remain intact. |

These are local value operations, not payment commands, ledger writes or authorization. No external rate lookup,
financial transaction, consumer database/schema migration or domain event is introduced. Catalog maintenance is
package-owned source maintenance, not runtime synchronization.

## Validation and permissions

Validate decimal syntax, requested rounding, currency recognition/provenance, supported precision, allocation
inputs and integer arithmetic. Do not use silent float casts or truncate excess precision. Supported value,
precision/mismatch, allocation and overflow failures use documented DomainException behavior; native enum/argument
errors retain documented PHP behavior rather than a blanket Error wrapper. Consumer policy owns accepted transaction currencies,
legal/accounting requirements, cash rounding, refunds, permissions and ledger composition. A historical Currency
is representable, not automatically authorized for use.

## Dependencies and sequencing

No new cross-TICKET prerequisite or execution priority. The approved complete PR-sized slices are:

- TASK-00139: exact Decimal; no TASK blockers, ready-for-agent.
- TASK-00140: versioned ISO Currency/catalog; no TASK blockers, needs-info for unverified source/data reuse evidence.
- TASK-00141: exact Money calculation/allocation/reader guarantees; blocked by TASK-00139 and TASK-00140.

Establish suitable reuse terms before catalog implementation/committing inputs; approval does not confer third-party
rights. Document evidence to clear TASK-00140 readiness, or pause for John if source permission/scope remains unclear.
Supported historical precision gaps remain explicit, not a reason to guess defaults. Factory/schema/catalog mechanics
remain routine design within the approved bounds/identity/rounding/reader promises. No temporal or financial-framework
prerequisite, source-copy mandate, new platform floor or terminal-evidence PR is required.

## Acceptance and evidence

- Cover exact Decimal parsing, equality/reconstruction and arithmetic/rounding at supported precision boundaries,
  including signs, zero and rejection cases. Demonstrate calculations that would be inaccurate with floats.
- Cover representative current/historical codes, numeric-code reuse, display metadata, different fraction digits
  and unavailable precision. Include the researched new/withdrawn-code cases without treating set differences
  as a complete economic-transition chronology.
- Cover signed Money, zero, same-currency operations, mismatch rejection and integer boundary/overflow behavior.
  Assert deterministic total-preserving allocation and validation of unsupported allocation inputs.
- Prove stable saved-amount meaning through supported round trips and metadata maintenance. Do not silently
  replace withdrawn currencies or conflate ISO exponents with cash subdivisions.
- Record primary-source catalog publication/provenance, historical precision evidence, gaps and applicable reuse
  terms. Document exact Decimal/Money representations, rounding, precision availability and consumer-policy limits.
- Classify new declarations, exceptions and representations under
  [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Each implementation TASK owns focused behavior evidence, the complete `./bin/build` gate, exact owned-production
  statement coverage and independent review. Do not add packaging/release-process tests or run certification here.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00139](../tasks/00139-TASK.md) | Add Exact Decimal Arithmetic | ready-for-agent |
| [TASK-00140](../tasks/00140-TASK.md) | Recognize Versioned ISO Currencies | needs-info |
| [TASK-00141](../tasks/00141-TASK.md) | Calculate and Allocate Exact Money | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved exact financial values, current/historical recognition and undefined-precision rejection
in [EPIC-00008](../epics/00008-EPIC.md), then approved this requirement area in the nine-TICKET split.
The EPIC records the inspected Novuso commit, official SIX sources and catalog findings. At that requirement
checkpoint this TICKET was ready for TASK planning only; no values/source changes/delivery were implemented or
authorized. Child status is generated above; [planning conventions](../CONVENTIONS.md) govern parent completion.

2026-10-06: John selected this TICKET and approved three complete code-first feature TASKs and their contracts:
[TASK-00139 — Add Exact Decimal Arithmetic](../tasks/00139-TASK.md),
[TASK-00140 — Recognize Versioned ISO Currencies](../tasks/00140-TASK.md) and
[TASK-00141 — Calculate and Allocate Exact Money](../tasks/00141-TASK.md).

Decimal/Currency are independent; Money depends on both. Approved contracts include normalized plain-string Decimal
and 1,024-digit/scale/4 KiB bounds, native RoundingMode semantics without floats, code-only Currency identity and
string numeric metadata, supported captured monetary scale, exact/explicit single rounding and largest-remainder
allocation with signed totals/original-index ties. No execution priority, automatic financial policy/adoption or
new required extension/external Domain dependency is approved. Factories/codec/catalog mechanics remain routine.

Source/data reuse evidence remains unverified; TASK-00140 stays needs-info, not executable catalog implementation,
until suitable terms/provenance are documented. John approving planning is not a license/redistribution waiver.
SIX Amendment 180 supplies primary historical BGN exponent 2; List Three alone does not resolve other historical
precision. Representative unavailable precision remains a supported Currency state and rejects fresh minor-unit
Money until a supported definition exists; saved supported reader definitions must retain their original meaning.

Saved XML rechecks confirmed 178 current/137 historical codes with eight overlapping, current exponents 0/2/3/4/N.A.
and no historical XML minor-unit fields. Native probes showed float approximation, unrepresentable rounded native
maximum casts (with a warning), minimum abs promotion and lost signed remainder on truncated negative splits.
Core-only PHP exposes RoundingMode without BCMath/GMP; that does not test a future Decimal backend. These are
orientation/reference-call-pattern evidence, not loaded Novuso execution, Common financial bug repair, verified reuse
permission, new product test acceptance, comprehensive historical precision qualification or full gate/coverage.

Each TASK owns its complete public capability, direct tests, compatibility/docs/CONTEXT.md, full gate/exact coverage
and independent review. This TICKET is decomposed, not done. TICKET-00038/TICKET-00039 still need TASK decomposition;
no financial API, source/data change, branch, worktree, commit, publication, merge, certification, signing, release
or deployment is implemented, authorized or claimed by planning.

Planning-only verification passed: `./bin/planning-check --write` and `./bin/planning-check` reported 181 records /
25 active; generated views were refreshed and tracked/new-record whitespace checks passed. TASK-00140 appears in
Needs Info and TASK-00141 waits on both financial dependencies. These checks are not product tests, a fresh full
gate/coverage result or implementation acceptance.
