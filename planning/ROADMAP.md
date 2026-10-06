# Roadmap

## Current EPICs

<!-- planning:epics -->
| EPIC ID | Title | Target | Status |
|---|---|---|---|
| [EPIC-00006](epics/00006-EPIC.md) | Reusable MCP Streamable HTTP Tool Support | next-minor | done |
| [EPIC-00007](epics/00007-EPIC.md) | Reusable MCP Resources and Structured Skills | unassigned | done |
| [EPIC-00008](epics/00008-EPIC.md) | Strengthen Value Objects and Validation Invariants | next-minor | ready-for-agent |
<!-- /planning:epics -->

## Planning frontier

These are decomposition actions, not executable TASKs. Inspect parent requirements and any missing decisions
before decomposition. Live and archived children both count. [Automatic parent completion](CONVENTIONS.md#automatic-parent-completion)
closes eligible parents during the child-completion operation. Execution remains on the [TASK Board](tasks/BOARD.md).

<!-- planning:frontier -->
| Parent ID | Title | Status | Planning action |
|---|---|---|---|
| None | — | — | — |
<!-- /planning:frontier -->

## Strategy and next decisions

The [TASK Board](tasks/BOARD.md) has no active implementation TASK at this checkpoint. Preparing the 1.3.0
release is a separately authorized release operation, not an unfinished product EPIC.

[EPIC-00008](epics/00008-EPIC.md) has nine approved requirement TICKETs. John selected TICKET-00031 through TICKET-00039
and approved nineteen complete PR-sized TASKs (one each for TICKET-00031 through TICKET-00034, three for TICKET-00035,
five for TICKET-00036, three for TICKET-00037, one for TICKET-00038 and three for TICKET-00039):

- [TASK-00127 — Make Validation Reuse Exception-Safe](tasks/00127-TASK.md), a regression-first bug repair.
- [TASK-00128 — Add Strict Pagination Construction](tasks/00128-TASK.md), a code-first additive feature with separate
  legacy limitation evidence, not an immediate legacy-constructor repair.
- [TASK-00129 — Add Immutable JSON Snapshots](tasks/00129-TASK.md), a code-first opt-in feature with snapshot-aware
  text reconstruction and fresh mutable outputs, leaving legacy factories and Doctrine hydration unchanged.
- [TASK-00130 — Give StreamId Tuple Value Semantics](tasks/00130-TASK.md), a direct Identifier promotion explicitly
  moved out of next-minor delivery into the following major because automatic collection/JSON dispatch changes.
- [TASK-00131 — Correct Quoted Email Part Extraction](tasks/00131-TASK.md), a regression-first repair preserving
  email representation, case-sensitive identity, separate canonicalization and existing Doctrine conversions.
- [TASK-00132 — Add Canonical IP Address Values](tasks/00132-TASK.md), a code-first additive IP-family hierarchy
  with normalization, strict family parsing and retained IPv4-mapped IPv6 identity.
- [TASK-00133 — Add Lexical E.164 Phone Numbers](tasks/00133-TASK.md), a code-first additive lexical value with
  explicit optional adoption and preserved string-based SMS addressing.
- [TASK-00134 — Add Calendar and Local Time Values](tasks/00134-TASK.md), Date/Time and native ISO-backed WeekDay.
- [TASK-00135 — Add Exact Elapsed Durations](tasks/00135-TASK.md), independent signed integer-microsecond Duration
  with exact checked conversions/arithmetic.
- [TASK-00136 — Construct Strict Zoned DateTimes](tasks/00136-TASK.md), explicit-zone/DST/instant/native semantics,
  blocked by TASK-00134.
- [TASK-00137 — Add Inclusive Calendar Date Ranges](tasks/00137-TASK.md), inclusive/single-date intervals,
  blocked by TASK-00134.
- [TASK-00138 — Add Half-Open Instant Ranges](tasks/00138-TASK.md), zone-independent instant membership/identity,
  blocked by TASK-00136.
- [TASK-00139 — Add Exact Decimal Arithmetic](tasks/00139-TASK.md), bounded normalized exact arithmetic and native
  RoundingMode semantics with an extension-free baseline; no TASK blockers.
- [TASK-00140 — Recognize Versioned ISO Currencies](tasks/00140-TASK.md), offline current/historical catalog and
  supported precision provenance; no TASK blockers, but needs-info for unresolved source/data reuse evidence.
- [TASK-00141 — Calculate and Allocate Exact Money](tasks/00141-TASK.md), captured scale/safe readers, checked
  compatible-unit arithmetic and signed largest-remainder allocation; blocked by TASK-00139 and TASK-00140.
- [TASK-00142 — Express Transport-Neutral Application Failures](tasks/00142-TASK.md), all eleven extensible categories,
  diagnostic/cause preservation, neutral retry hints and unchanged structured validation; no TASK blockers.
- [TASK-00143 — Present Safe Errors Through PSR-15](tasks/00143-TASK.md), shared safe projection/diagnostics through
  a complete opt-in middleware path, with separate legacy compatibility; blocked by TASK-00142.
- [TASK-00144 — Integrate Safe Symfony Error Handling](tasks/00144-TASK.md), native exception lifecycle/negotiation,
  reporting ownership and deprecated-path migration; blocked by TASK-00143.
- [TASK-00145 — Integrate Safe Laravel Error Handling](tasks/00145-TASK.md), native rendering/reporting composition
  and legacy controller/provider compatibility; blocked by TASK-00143, independent of TASK-00144.

The first seven TASKs and TASK-00134/TASK-00135/TASK-00139/TASK-00140/TASK-00142 have no TASK dependencies;
temporal/Money slices retain their true dependencies above. Currency's missing reuse evidence is explicit, not silently cleared by planning
approval or represented by a fake blocker TASK. No execution priority is assigned. TASK-00130's execution/integration
must respect its major target; no branch/timing allocation or minor exception is implied. All nine TICKETs are now
decomposed; consult the Board for execution selection and outstanding decisions. TICKET-00039's shared PSR-15 slice
consumes TASK-00142's vocabulary before the independent Symfony/Laravel integrations. New category existence and
planning approval do not contain current legacy disclosure. Planning has not started implementation, secured any
consumer, waived framework support evidence or approved an exact release.

The EPIC covers value objects, validation invariants, transport-neutral application failures and opt-in safe HTTP
error presentation. The primary target remains additive/deprecation-first next-minor delivery; TICKET-00034's direct
StreamId promotion is an explicitly linked following-major slice, not part of that minor. Incompatible enforcement
and replacement of legacy error-handling defaults also belong in a following major. TICKET-00039 consumes TICKET-00038's
application failure vocabulary; other areas have no new cross-TICKET prerequisite. Other potential 2.0 work begins
with fresh planning in the repository that owns the proposed scope; do not reopen archived work as an active commitment.

## Completed and retired work

Use the [EPIC archive](epics/archive/README.md), [TICKET archive](tickets/archive/README.md), and
[TASK archive](tasks/archive/README.md). Historical completion narratives remain in their records; current
status is generated from metadata.
