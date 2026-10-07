# TASK Board

Execution view for Fight Common. TASK records own status, dependencies, priority (`order`), and PR links.
Refresh generated rows with `./bin/planning-check --write`. A PR link does not assert merge or deployment.

## What's next?

For `/ask-matt` or an unqualified "What's next?", report the current Human Action or Needs Info decision and the
active TASK. Otherwise return the first executable TASK in Ready Frontier. Do not select by ID alone.

<!-- planning:board -->
## Active Work

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| — | [TASK-00131](00131-TASK.md) | Correct Quoted Email Part Extraction | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00132](00132-TASK.md) | Add Canonical IP Address Values | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00133](00133-TASK.md) | Add Lexical E.164 Phone Numbers | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00134](00134-TASK.md) | Add Calendar and Local Time Values | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00135](00135-TASK.md) | Add Exact Elapsed Durations | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00139](00139-TASK.md) | Add Exact Decimal Arithmetic | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | ready-for-agent | — | — |
| — | [TASK-00142](00142-TASK.md) | Express Transport-Neutral Application Failures | [TICKET-00038 — Express Transport-Neutral Application Failures](../tickets/00038-TICKET.md) | ready-for-agent | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| — | [TASK-00136](00136-TASK.md) | Construct Strict Zoned DateTimes | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | ready-for-agent | [TASK-00134](00134-TASK.md) | — |
| — | [TASK-00137](00137-TASK.md) | Add Inclusive Calendar Date Ranges | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | ready-for-agent | [TASK-00134](00134-TASK.md) | — |
| — | [TASK-00138](00138-TASK.md) | Add Half-Open Instant Ranges | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | ready-for-agent | [TASK-00136](00136-TASK.md) | — |
| — | [TASK-00141](00141-TASK.md) | Calculate and Allocate Exact Money | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | ready-for-agent | [TASK-00139](00139-TASK.md), [TASK-00140](00140-TASK.md) | — |
| — | [TASK-00143](00143-TASK.md) | Present Safe Errors Through PSR-15 | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | ready-for-agent | [TASK-00142](00142-TASK.md) | — |
| — | [TASK-00144](00144-TASK.md) | Integrate Safe Symfony Error Handling | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | ready-for-agent | [TASK-00143](00143-TASK.md) | — |
| — | [TASK-00145](00145-TASK.md) | Integrate Safe Laravel Error Handling | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | ready-for-agent | [TASK-00143](00143-TASK.md) | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| — | [TASK-00140](00140-TASK.md) | Recognize Versioned ISO Currencies | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | needs-info | — | — |

## Human Action

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Needs Triage

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Recently Closed

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00103](00103-TASK.md) | Adopt the EPIC, TICKET, and TASK planning surface | — (standalone chore) | done | — | [PR #155](https://github.com/johnnickell/fight-common/pull/155) |
| 1 | [TASK-00104](00104-TASK.md) | Establish MCP protocol semantics and truthful capability discovery | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | done | — | [PR #157](https://github.com/johnnickell/fight-common/pull/157) |
| 2 | [TASK-00105](00105-TASK.md) | Serve guarded stateless MCP requests through PSR HTTP | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | done | — | [PR #163](https://github.com/johnnickell/fight-common/pull/163) |
| 3 | [TASK-00106](00106-TASK.md) | Register and discover explicitly opted-in MCP Tools | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | done | — | [PR #165](https://github.com/johnnickell/fight-common/pull/165) |
| 4 | [TASK-00107](00107-TASK.md) | Invoke validated CQRS MCP Tools with safe semantic output | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | done | — | [PR #166](https://github.com/johnnickell/fight-common/pull/166) |
| 5 | [TASK-00108](00108-TASK.md) | Orchestrate request-scoped MCP progress and cooperative cancellation | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | done | — | [PR #167](https://github.com/johnnickell/fight-common/pull/167) |
| 6 | [TASK-00109](00109-TASK.md) | Deliver Fight Common progressive MCP HTTP and SSE adapters | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | done | — | [PR #168](https://github.com/johnnickell/fight-common/pull/168) |
| 7 | [TASK-00110](00110-TASK.md) | Qualify progressive MCP in every supported framework starter | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | wontfix | — | [PR #169](https://github.com/johnnickell/fight-common/pull/169) |
| 8 | [TASK-00111](00111-TASK.md) | Protect and resume ordinary MCP input_required interactions | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | done | — | [PR #171](https://github.com/johnnickell/fight-common/pull/171) |
| 9 | [TASK-00112](00112-TASK.md) | Atomically resume destructive MCP confirmations | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | done | — | [PR #172](https://github.com/johnnickell/fight-common/pull/172) |
| 10 | [TASK-00113](00113-TASK.md) | Compose policy-free OAuth resource-server protection for MCP | [TICKET-00027 — Protect MCP Endpoints with Reusable OAuth Resource-Server Support](../tickets/00027-TICKET.md) | done | — | [PR #174](https://github.com/johnnickell/fight-common/pull/174) |
| 11 | [TASK-00121](00121-TASK.md) | Bound MCP Request Reading and Decoding | [TICKET-00030 — Compose Resources, Skills and Tools Through One Guarded Endpoint](../tickets/00030-TICKET.md) | done | — | [PR #181](https://github.com/johnnickell/fight-common/pull/181) |
| 12 | [TASK-00122](00122-TASK.md) | Deliver the Combined Tools, Resources and Skills Journey | [TICKET-00030 — Compose Resources, Skills and Tools Through One Guarded Endpoint](../tickets/00030-TICKET.md) | done | — | [PR #182](https://github.com/johnnickell/fight-common/pull/182) |
| 13 | [TASK-00123](00123-TASK.md) | Introduce an Optional Metadata-Aware MCP Capability Contract | — (standalone chore) | done | — | [PR #186](https://github.com/johnnickell/fight-common/pull/186) |
| 14 | [TASK-00124](00124-TASK.md) | Defer Dynamic Resource-Provider Validation Until Guarded Dispatch | — (standalone chore) | done | — | [PR #187](https://github.com/johnnickell/fight-common/pull/187) |
| 15 | [TASK-00125](00125-TASK.md) | Reconcile v1.3 MCP documentation and prepare release qualification | — (standalone chore) | done | — | [PR #188](https://github.com/johnnickell/fight-common/pull/188) |
| — | [TASK-00114](00114-TASK.md) | Reconcile PR #159 strict PHPCS delivery evidence | — (standalone chore) | done | — | [PR #159](https://github.com/johnnickell/fight-common/pull/159) |
| — | [TASK-00115](00115-TASK.md) | Adopt approved planning, ownership, and independent review conventions | — (standalone chore) | done | — | [PR #173](https://github.com/johnnickell/fight-common/pull/173) |
| — | [TASK-00116](00116-TASK.md) | Restore DBAL schema-builder compatibility with supported dependencies | — (standalone bug) | done | — | [PR #162](https://github.com/johnnickell/fight-common/pull/162) |
| — | [TASK-00117](00117-TASK.md) | Discover Authorized Resources Through the Guarded Endpoint | [TICKET-00028 — Discover and Read Authorized Resources](../tickets/00028-TICKET.md) | done | — | [PR #175](https://github.com/johnnickell/fight-common/pull/175) |
| — | [TASK-00118](00118-TASK.md) | Read Exact Authorized Resource Content | [TICKET-00028 — Discover and Read Authorized Resources](../tickets/00028-TICKET.md) | done | — | [PR #176](https://github.com/johnnickell/fight-common/pull/176) |
| — | [TASK-00119](00119-TASK.md) | Serve Validated Immutable Skill Revisions as Resources | [TICKET-00029 — Discover Structured Skills and Retrieve Revision Files Lazily](../tickets/00029-TICKET.md) | done | — | [PR #177](https://github.com/johnnickell/fight-common/pull/177) |
| — | [TASK-00120](00120-TASK.md) | Discover and Get Complete Authorized Skill Entries | [TICKET-00029 — Discover Structured Skills and Retrieve Revision Files Lazily](../tickets/00029-TICKET.md) | done | — | [PR #178](https://github.com/johnnickell/fight-common/pull/178) |
| — | [TASK-00126](00126-TASK.md) | Create certification archives from pristine release content | — (standalone chore) | done | — | [PR #193](https://github.com/johnnickell/fight-common/pull/193) |
| — | [TASK-00127](00127-TASK.md) | Make Validation Reuse Exception-Safe | [TICKET-00031 — Safely Reuse Validation Services](../tickets/00031-TICKET.md) | done | — | [PR #195](https://github.com/johnnickell/fight-common/pull/195) |
| — | [TASK-00128](00128-TASK.md) | Add Strict Pagination Construction | [TICKET-00032 — Construct Safe Pagination Requests](../tickets/00032-TICKET.md) | done | — | [PR #196](https://github.com/johnnickell/fight-common/pull/196) |
| — | [TASK-00129](00129-TASK.md) | Add Immutable JSON Snapshots | [TICKET-00033 — Snapshot JSON Without Mutable Aliasing](../tickets/00033-TICKET.md) | done | — | [PR #197](https://github.com/johnnickell/fight-common/pull/197) |
| — | [TASK-00130](00130-TASK.md) | Give StreamId Tuple Value Semantics | [TICKET-00034 — Treat Stream Identifiers as Values](../tickets/00034-TICKET.md) | done | — | [PR #198](https://github.com/johnnickell/fight-common/pull/198) |
<!-- /planning:board -->

## Wayfinder

Consult the [map index](../wayfinder/README.md) for current charting state. A map's authored Frontier identifies
the next decision; a closed map does not create a new implementation commitment.

## History

Completed planning remains in the [TASK archive](archive/README.md). The [migration map](../MIGRATION.md) resolves
old IDs and paths. Archive records only on an explicit request.
