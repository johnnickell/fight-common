---
id: TICKET-00037
epic: EPIC-00008
title: Calculate Exact Monetary Values
status: ready-for-agent
---

# Calculate Exact Monetary Values

## Problem statement

Consumers need reusable exact decimal and monetary calculations without binary-float approximation, guessed
currency scale or saved amounts being reinterpreted by later definition changes. This package has no existing
Money, Currency or Decimal public API.

## Solution and boundaries

- Add exact string-based Decimal with whole-string signed plain ASCII decimal syntax, no exponent/separators/
  whitespace/float factories and normalized numeric identity (1, 1.0 and +01.000 equal; negative zero is zero).
  Bounds are 1,024 normalized coefficient digits, normalized/requested scale 0–1,024 and 4 KiB input. Exact
  arithmetic and finite division reject unsupported results; scaled/rounded operations require explicit native
  PHP RoundingMode. Use a plain-PHP baseline without required BCMath/GMP/third-party Domain arithmetic or floats.
- Add immutable Currency with the **exact 49-code, 0/2/3 accounting-exponent set in TASK-00140**. Construction is
  offline and rejects all unsupported codes without a guessed default. Alphabetic code is identity; attached
  scale/definition is immutable and has a stable version for later saved Money reads. The set is a deliberate
  package promise, not a rank order, global catalog or claim of transaction eligibility. More codes need
  deliberate additions; neither broad historical coverage nor external currency documents are required.
- Add signed native-integer minor-unit Money, including PHP_INT_MIN without a new 64-bit floor, and checked
  compatible-currency/scale arithmetic. Capture the supported accounting scale/definition at construction;
  exact conversion/scalar calculation rejects fractional minor results unless explicit rounding is selected,
  with one monetary-boundary rounding decision. Identity reflects code, captured scale and amount.
- Allocate a nonempty list of nonnegative integer weights with at least one positive weight using largest
  remainder, ties by original index. Compute magnitude/intermediates exactly, restore sign and original order,
  give zero weights zero shares and preserve positive/zero/negative totals including native minimum.
- Supported definition changes must not reinterpret saved scale, replace codes or silently redenominate. Versioned
  reconstruction retains exact textual minor units, code/scale and supported definition context; readers retain
  old meaning. Unknown codes, scales/definitions or malformed input reject predictably.
- Decimal, Currency and Money are new Fight APIs; no external constructor/metadata/serialization compatibility
  is required. Do not copy or vendor a comprehensive third-party currency table. Exclude exchange rates,
  automatic conversion, localized formatting, arbitrary application financial policy and network lookup.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer parses, calculates or rounds exact decimals | N/A; value operation | N/A; local calculation | N/A; no mutation | Exact result or explicit rounding/rejection without float approximation. |
| Consumer identifies a supported currency | N/A; value construction | N/A; package-owned definition lookup | N/A; no mutation | Stable code identity and known accounting exponent; unsupported codes reject. |
| Consumer constructs/calculates signed minor-unit Money | N/A; value operation | N/A; local calculation | N/A; no mutation | Correct same-currency result with checked representability. |
| Consumer supplies mismatched currency, scale or overflow | N/A; value operation | N/A; local checks | N/A; no mutation | Predictable rejection instead of coercion or guessed scale. |
| Consumer allocates signed Money | N/A; value operation | N/A; local calculation | N/A; no mutation | Deterministic allocations whose exact sum equals the original amount. |
| Consumer reconstructs saved Money after definitions change | N/A; value reconstruction | N/A; local decoding | N/A; no mutation | Original code, minor units and scale retain meaning. |

These are local value operations, not payments, ledger writes or authorization. No new business command, event,
consumer database/schema migration or transport is introduced. Definition maintenance is package source work,
not runtime synchronization.

## Validation and permissions

Validate decimal syntax/rounding, listed Currency recognition and 0/2/3 exponent, allocation inputs and integer
bounds. Do not silently float-cast, truncate excess precision or infer a scale from an unsupported code. Supported
validation, mismatch, allocation and overflow failures use documented DomainException behavior; native enum/
argument errors retain documented PHP behavior. Consumers own accepted transaction currencies, accounting/legal
policy, cash rounding, refunds, permissions and ledger composition. Recognizing a code confers no authority.

## Dependencies and sequencing

No cross-TICKET prerequisite or execution priority. Three approved complete PR-sized slices:

- [TASK-00139 — Add Exact Decimal Arithmetic](../tasks/00139-TASK.md): independent and ready-for-agent.
- [TASK-00140 — Define 49 Common Currency Values](../tasks/00140-TASK.md): independent and ready-for-agent after
  John's bounded-set decision replaces the earlier broad-data question.
- [TASK-00141 — Calculate and Allocate Exact Money](../tasks/00141-TASK.md): waits for both Decimal and Currency.

Factory/codec/definition mechanics remain routine design within the fixed supported set, stable saved meaning and
rounding promises. No temporal framework, new platform floor or separate terminal-evidence PR is required.

## Acceptance and evidence

- Cover Decimal parsing, numeric identity, reconstruction and exact arithmetic/rounding at supported boundaries,
  including signs, zero, rejection and results that binary floats misrepresent.
- Verify all 49 supported codes and their 0/2/3 scales; exercise different scales, unknown/malformed codes,
  definition retention and the absence of inferred transaction/cash policy. No completeness claim.
- Cover signed Money, zero, same-currency operations, scale/currency mismatch rejection, native integer bounds and
  deterministic signed total-preserving allocation.
- Prove saved-amount meaning across supported round trips and controlled definition maintenance. Never silently
  substitute a changed scale or currency.
- Document the owned supported set and maintenance contract, exact Decimal/Money representations, rounding and
  consumer policy limits in product guidance, without external currency-source links.
- Classify new declarations, exceptions and representations under
  [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Each implementation TASK owns focused behavior evidence, complete `./bin/build`, exact owned-production statement
  coverage and independent review. No packaging/release-process tests or certification here.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00139](../tasks/00139-TASK.md) | Add Exact Decimal Arithmetic | done |
| [TASK-00140](../tasks/00140-TASK.md) | Define 49 Common Currency Values | done |
| [TASK-00141](../tasks/00141-TASK.md) | Calculate and Allocate Exact Money | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved the original financial requirement and three code-first TASKs. The original Currency
scope was much broader and was held for a data question; no financial API or product gate was implemented.

2026-10-09: John replaced that proposal with the exact 49 codes in TASK-00140: the 39 individually reported
codes previously inspected, minus BGN, plus BDT, EGP, KES, KWD, LKR, MAD, NGN, PKR, QAR, UAH and VND. The
shortlist is curated, not a ranked top-49 or a complete catalog. Its 0/2/3 exponent definitions are package-owned
contract choices. No external currency-table redistribution, current/historical catalog or unsupported scale
fallback is planned. TASK-00140 now needs implementation, not further source-permission research; TASK-00141
still depends on completion of both TASK-00139 and TASK-00140. The old approval did not grant implementation,
publication, merge, certification, signing or release authority. Every TASK still owns its verification/review.
