---
id: TICKET-00036
epic: EPIC-00008
title: Model Calendar Time and Instant Boundaries
status: ready-for-agent
---

# Model Calendar Time and Instant Boundaries

## Problem statement

Consumers need reusable values distinguishing calendar dates, local times, zoned instants, fixed elapsed time and
intervals. Native parsing alone can silently normalize nonexistent DST times or select the wrong overlap
occurrence. A focused temporal suite should make those invariants explicit without replacing existing timestamp
APIs or importing Novuso's broader scheduling/sequence semantics.

## Solution and boundaries

- Add proleptic Gregorian Date for years 0001–9999 (`YYYY-MM-DD`), local Time with exact microseconds
  (`HH:MM:SS.ffffff`, 00:00:00.000000 through 23:59:59.999999) and zoned DateTime using existing Timezone. Reject
  rollover, 24:00 and leap seconds. Native component extraction uses the supplied timestamp's own local context.
- Add native integer-backed WeekDay matching PHP's w convention, Sunday=0 through Saturday=6; retain native enum
  from/tryFrom/JSON behavior. Date weekday calculation uses PHP with explicit internal UTC, not ambient-zone defaults.
  Date/Time have coherent canonical equality/hash/chronological comparison and reconstruction.
- DateTime equality is exact instant plus native timezone identifier; compare instant first, identifier second,
  returning -1/0/1 coherently with equality. Expose separate same-instant comparison, provisionally isSameInstantAs,
  and owned instant semantics usable by intervals. Preserve distinct IANA aliases and native identifier spelling;
  normalize offset spelling as native DateTimeZone does without changing existing Timezone equality/spelling.
- Require explicit timezone context for zoned construction. Reject nonexistent DST-gap local times and require
  a valid explicit offset in seconds for repeated overlap times. Validate supplied offsets against real occurrences;
  support non-hour transitions, historical second offsets and existing fixed-offset/abbreviation timezone contexts.
- Preserve exact instants, microseconds and native timezone identifiers through native/serialized construction,
  including either overlap occurrence. Detach mutable native input and retain exact instant coordinates/identifier,
  not only wall-clock components or a minute-only offset. Handle pre-epoch fractions without float interpretation.
- Distinguish instant-preserving timezone conversion from reinterpretation of local date/time components. Neither
  operation may silently masquerade as the other; reinterpretation must obey construction invariants.
- Add signed fixed elapsed Duration as integer microseconds bounded by PHP_INT_MIN/PHP_INT_MAX, without a new
  platform floor. Exact integer-unit conversions reject fractional/unrepresentable requests; addition/subtraction/
  negation check overflow, including minimum-integer negation. Zero/negative values are valid; positivity belongs
  to consuming timeout/TTL policy, not Duration. Calendar months/years are excluded.
- Add inclusive DateRange: reject reversed dates, include both endpoints, equal dates form a one-date range;
  equality/hash reflects endpoint dates. Add half-open InstantRange `[start, end)`: validate/contain by instant only,
  equal instants form an empty interval even with different zones, and equality/hash reflects the two instant
  endpoints independent of timezone labels. Preserve precision and supported reconstruction; no interval-length API
  or Duration dependency is required.
- Preserve public DateTimeImmutable timestamp contracts and existing audit lookup's inclusive endpoints. Adoption
  is optional; adding these values does not change messaging, token, repository or scheduler policy.
- Exclude stepped sequences, recurring scheduling, calendar-month/year durations, ambient timezone defaults,
  native API replacement and automatic conversion of existing consumer data.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer represents a calendar date, local time or weekday | N/A; value construction | N/A; local access | N/A; no domain mutation | Valid distinct calendar/local concepts with stable representation. |
| Consumer constructs a zoned local time at a DST gap/overlap | N/A; value construction | N/A; local timezone rules | N/A; no domain mutation | Reject a gap; require and validate overlap disambiguation. |
| Consumer imports/exports native timestamps, including both overlap occurrences | N/A; value conversion | N/A; local conversion | N/A; no domain mutation | Preserve instant, microseconds and timezone through supported round trips. |
| Consumer compares zoned values or changes timezone | N/A; value operation | N/A; local comparison/conversion | N/A; no domain mutation | Distinguish value equality from same-instant identity and conversion from reinterpretation. |
| Consumer converts a signed fixed duration | N/A; value operation | N/A; local calculation | N/A; no domain mutation | Exact elapsed microseconds with explicit conversion behavior. |
| Consumer tests calendar/instant interval membership | N/A; value operation | N/A; local comparison | N/A; no domain mutation | Approved inclusive/half-open, reversed/equal and empty-endpoint behavior. |
| Existing consumer uses native timestamps or audit lookup | N/A; existing workflow | Existing capability reads; no new query type | N/A; no new event type | Existing native and inclusive-audit contracts remain unchanged. |

No new persistence/schema writes, business messages, scheduling effects or external I/O are required. These values
use timezone rules, not application authorization or proof that an operation occurred at a particular time.

## Validation and permissions

Validate Gregorian dates and time components without accidental normalization. Apply the strict combined DST
invariant to construction, reconstruction and local reinterpretation. Document supported year/numeric ranges and
representable duration/conversion limits during TASK design; reject unsupported values predictably. Authorization
is N/A; consumers own timeout bounds, calendar policy, schedules and access to time-associated records.

## Dependencies and sequencing

No new cross-TICKET prerequisite. The approved complete PR-sized slices are:

- TASK-00134: Date/Time/WeekDay; no blockers.
- TASK-00135: exact Duration; no blockers or dependency on the other temporal slices.
- TASK-00136: strict zoned DateTime; blocked by TASK-00134, reusing existing Timezone.
- TASK-00137: inclusive DateRange; blocked by TASK-00134, not DateTime or Duration.
- TASK-00138: half-open InstantRange; blocked by TASK-00136, transitively TASK-00134, not Duration or DateRange.

No execution priority is assigned. Factory signatures, supported noncanonical spellings and exact codec/storage
mechanics remain routine design within approved bounds/identity/failure promises, not permission to inherit
ambiguous Novuso wall-time reconstruction, native normalization or a new platform requirement.

## Acceptance and evidence

- Cover Gregorian validity, leap dates, local time/microsecond bounds and weekday behavior, with stable value
  equality/hash/comparison and supported string/JSON round trips.
- Prove rejection of DST gaps and undisambiguated overlaps. Exercise both valid overlap occurrences, invalid
  disambiguation and preservation through native import/export and serialized reconstruction.
- Prove timezone-sensitive equality alongside same-instant comparison; exercise conversion versus local
  reinterpretation, including transitions and rejection paths. Use instant ordering for interval membership.
- Cover signed/zero/positive Duration, exact microseconds, supported conversion boundaries and predictable failure
  where a requested exact conversion or arithmetic result is unrepresentable.
- Cover inclusive DateRange and half-open InstantRange containment, reversed endpoints, single-date ranges and
  empty instant intervals. Demonstrate preserved native timestamp and audit lookup behavior where affected.
- Deliberately classify declarations, formats and exception contracts under
  [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md). Verify applicable licensing before adapting Novuso.
- Document temporal concepts, explicit timezone/DST behavior, native interoperability, range boundaries and optional
  adoption. Each implementation TASK owns the complete `./bin/build` gate, exact owned-production statement
  coverage and independent review; there is no separate terminal evidence TICKET.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00134](../tasks/00134-TASK.md) | Add Calendar and Local Time Values | done |
| [TASK-00135](../tasks/00135-TASK.md) | Add Exact Elapsed Durations | ready-for-agent |
| [TASK-00136](../tasks/00136-TASK.md) | Construct Strict Zoned DateTimes | ready-for-agent |
| [TASK-00137](../tasks/00137-TASK.md) | Add Inclusive Calendar Date Ranges | ready-for-agent |
| [TASK-00138](../tasks/00138-TASK.md) | Add Half-Open Instant Ranges | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

**Current weekday amendment:** after initial TASK-00134 landing, John explicitly selected Sunday=0 through
Saturday=6 (PHP `w`) and PHP-native weekday calculation. This supersedes the original ISO numbering recorded in
the historical checkpoints below; all other temporal bounds/contracts remain unchanged. TASK-00134 owns the
unreleased source/test/docs/manifest revision and fresh verification/review/QA. Internal UTC is a calculation detail,
not a consumer timezone or public timestamp conversion. See its owner-amendment section for authority and delivery
state; previously accepted ISO evidence remains historical, not acceptance of the amended contract.

2026-10-06: John approved the focused temporal suite, zoned equality, strict DST construction and equal-endpoint
semantics in [EPIC-00008](../epics/00008-EPIC.md), then approved this requirement area in its nine-TICKET split.
At that requirement checkpoint the TICKET was ready for decomposition only, without new temporal APIs or delivery
claims. Novuso is reference evidence, not a compatibility target; its wall-clock reconstruction can lose overlap
identity. Child status is generated above; parent completion follows [planning conventions](../CONVENTIONS.md).

2026-10-06: John selected this TICKET and approved five code-first feature TASKs, their dependency edges and contracts:
[TASK-00134 — Add Calendar and Local Time Values](../tasks/00134-TASK.md),
[TASK-00135 — Add Exact Elapsed Durations](../tasks/00135-TASK.md),
[TASK-00136 — Construct Strict Zoned DateTimes](../tasks/00136-TASK.md),
[TASK-00137 — Add Inclusive Calendar Date Ranges](../tasks/00137-TASK.md) and
[TASK-00138 — Add Half-Open Instant Ranges](../tasks/00138-TASK.md).

Approved bounds are proleptic Gregorian years 0001–9999, local six-digit microseconds, ISO Monday=1 weekday backing
and signed native-integer Duration. Exact conversions/arithmetic reject fractional/unrepresentable outcomes.
DateTime uses native timezone identifier plus exact instant identity, validates offset-in-seconds overlap selection,
rejects gaps and distinguishes instant conversion from local reinterpretation. Range identities follow Date versus
instant endpoints, with inclusive single-date and half-open empty behavior respectively. Existing Timezone spelling/
equality and native messaging/audit timestamp/inclusive-query contracts remain unchanged; adoption is optional.

Read-only native probes confirmed New York gap normalization/overlap default selection, loss of the second fold
when reconstructing only local components, Paris's 561-second historical offset despite minute-only P display,
pre-epoch native seconds/fraction semantics and fixed-offset/abbreviation contexts without transition tables.
These are orientation evidence, not an existing Common temporal repair, loaded Novuso integration, product test
run, fresh full gate/coverage or implementation acceptance.

Each TASK owns its complete public outcome, meaningful tests, compatibility/docs/CONTEXT.md, full gate/exact coverage
and independent review. There is no tests-only, base-only or terminal-evidence PR and no assigned execution priority.
This TICKET is decomposed, not done. TICKET-00037 through TICKET-00039 still need TASK decomposition; no source change,
implementation branch, worktree, commit, publication, merge, certification, signing, release or deployment is
authorized or claimed by planning.

Planning-only verification passed: `./bin/planning-check --write` and `./bin/planning-check` reported 178 records /
22 active; generated views were refreshed and tracked/new-record whitespace checks passed. Dependency edges are
recorded and derived as waiting work; no execution priority or product test/full-gate/coverage/acceptance evidence
is inferred from these checks.
