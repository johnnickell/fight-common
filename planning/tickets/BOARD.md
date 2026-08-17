# Ticket Board

Operational execution view for Fight Common. Ticket files are canonical for status and blocking edges; this
board is canonical for recommended order. IDs identify artifacts only. Update this file whenever ticket
status, dependencies, or roadmap priority changes.

Last updated: 2026-08-16

## “What’s Next?” Contract

When `/ask-matt` or a plain “What’s next?” is invoked:

1. **Human decision:** return the item under **Now** when it still requires judgment.
2. **Implementation:** return the first ticket under **Ready Frontier**.
3. If the question is unqualified, return both targets. Never choose by ticket number alone.

## Now

No human decision is currently active.

## Ready Frontier

These tickets have no unfinished blockers. Work top to bottom unless current context makes another ready
ticket materially cheaper.

| Rank | Ticket | Parent PRD | Why Next |
|------|--------|------------|----------|
| 8 | [T-00032 — Establish Release Inspection, Plans, and Boundary Fakes](00032-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | Opens the read-only inspection and immutable planning journey after the Wayfinder handoff. |
| 9 | [T-00047 — Establish the Public API Authority and Consumer Harness](00047-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Establishes the authoritative compatibility baseline and consumer seam required by every Fight Common contract-repair slice. |
| 10 | [T-00057 — Pin Fight Common's Symfony Components to the Current Supported Line](00057-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Aligns Fight Common's own Symfony floor with the current-only window used by downstream repository-owned compatibility lanes. |
| 11 | [T-00058 — Publish the Normative Supported-Line Window and Activation Suggestions](00058-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Records the supported window and activation packages as normative consumer documentation. |
| 12 | [T-00061 — Bootstrap the Private Fight AccessControl Repository and Transfer Authority](00061-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Establishes the first repository-local authority and opens the parallel starter-foundation wave without waiting for a public tag. |

## Waiting

All tickets retain `ready-for-agent`; their position here is derived from unfinished blocking edges.

| Suggested Order | Ticket | Parent PRD | Waiting On |
|-----------------|--------|------------|------------|
| 21 | [T-00040 — Prove Resumable Release Runs and Phase Handoffs](00040-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | T-00032 |
| 23 | [T-00033 — Prove the Normal Feature Package Journey](00033-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00040 |
| 24 | [T-00034 — Compose Certification Evidence and Compatibility Lanes](00034-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00033 |
| 25 | [T-00035 — Publish the Signed Tag and Immutable GitHub Release](00035-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00034 |
| 26 | [T-00041 — Verify Packagist Projection and Clean Installation](00041-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00035 |
| 27 | [T-00036 — Implement Maintenance-Line Lifecycle Decisions](00036-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00033, T-00034 |
| 28 | [T-00037 — Release the Oldest Affected Supported-Line Patch](00037-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00036, T-00041 |
| 29 | [T-00042 — Forward-Port Patches Through Newer Affected Lines](00042-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00037 |
| 30 | [T-00038 — Add Release Skills and Catalog Routing](00038-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00041, T-00042 |
| 31 | [T-00043 — Add the State-First Dispatcher and Journey-Card Runbook](00043-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00038 |
| 32 | [T-00039 — Integrate CI and Validate the Final Epic Handoff](00039-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00043 |
| 34 | [T-00048 — Restore Scheduler 1.x Construction Compatibility](00048-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 35 | [T-00049 — Deliver Typed JSend Through the Symfony Response Boundary](00049-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 36 | [T-00050 — Publish Canonical Symfony HTTP, Filesystem, and Routing Paths](00050-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00049 |
| 37 | [T-00051 — Publish Canonical Symfony Messaging Paths](00051-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 38 | [T-00052 — Publish Canonical Symfony Event Sourcing and Templating Paths](00052-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 39 | [T-00053 — Publish Canonical Doctrine Data Type Paths](00053-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 40 | [T-00054 — Prove Root Dependency Modes and Production Package Isolation](00054-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 41 | [T-00059 — Publish the Additive Transactional UnitOfWork Boundary](00059-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 42 | [T-00060 — Publish Private Realtime Updates Through Mercure](00060-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00047 |
| 43 | [T-00056 — Certify the Fight Common 1.2 Compatibility Envelope](00056-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | T-00034, T-00048 through T-00054, T-00059, T-00060 |
| 44 | [T-00062 — Bootstrap the Private Symfony Starter and Transfer Authority](00062-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 |
| 45 | [T-00063 — Bootstrap the Private Laravel Starter and Transfer Authority](00063-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 |
| 46 | [T-00064 — Bootstrap the Private Yii Starter and Transfer Authority](00064-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 |
| 47 | [T-00065 — Bootstrap the Private CodeIgniter Starter and Transfer Authority](00065-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 |
| 48 | [T-00066 — Bootstrap the Private Slim Starter and Transfer Authority](00066-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 |
| 49 | [T-00067 — Verify All Six Repository Handoffs and Close WF-018](00067-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | T-00061 through T-00066 |

## Needs Info

No tickets currently require a decision authority.

## Final Priority

This ticket is the final `1.2` acceptance boundary and remains waiting until its explicit compatibility
certification blocker and every higher-priority release item are complete.

| Rank | Ticket | Parent PRD | Why Last |
|------|--------|------------|----------|
| 50 | [T-00017 — Complete 1.2 Compatibility and Release Acceptance](00017-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Prove repository-wide additive compatibility and close the 1.2 acceptance boundary only after T-00056 certifies the complete Fight Common contract evidence. |

The completed projection path now combines ordered at-least-once handling and fail-stop retry with durable,
named monotonic DBAL checkpoints proven on SQLite, MySQL, and PostgreSQL. Synchronous dispatcher failure isolation,
in-memory event publication, durable named publication cursors, transactional failure evidence, and composable PSR-3
logging are complete across the same database matrix. T-00016 now documents the complete integration and
operations surface through single-source executable SQLite DBAL examples, including the delivered Symfony provider
autoconfiguration path that composes private, dependency-injected mapping providers through the portable Event
Mapper contract. T-00017 remains the board's final priority and now explicitly waits on T-00056 so `1.2`
compatibility and release acceptance cannot be declared before the complete contract, package, quality-gate,
and release-coordination evidence exists. The
quality-gate path is complete: the canonical coding standard, its mechanical, member-layout, and semantic
documentation migrations, and its reusable fixer repairs are complete without baselines or suppressed legacy
violations. Architecture enforcement is complete with exact layer allowances, mandatory unassigned-token
failure, and Scheduler command execution through the required `ProcessRunner`. Core iterator, timezone, and
validation-service coverage is exact without inline exclusions or test-order dependence. Filesystem,
template-buffering, StatsD, process, FTP, and Scheduler failure boundaries are now deterministic without
public-contract or production filesystem-semantic changes. The permanent coverage gate now rejects every
production exclusion directive and fails closed unless the provided Clover project metrics prove exact statement
equality. T-00028 composes those contracts into the shared host-neutral gate, T-00029 wraps it in the canonical
non-interactive disposable local build with locked and latest-compatible dependency modes, and T-00030 runs the
shared gate directly in hosted CI after ephemeral latest-compatible dependency resolution. T-00031 completes the
path with opt-in tracked pre-commit enforcement that delegates to the default local build without duplication.

The release path starts at T-00032 and T-00040, then proves packaging and certification through T-00033 and
T-00034. GitHub publication continues through T-00035 and downstream verification through T-00041, while
maintenance lifecycle work may proceed independently at T-00036 after certification. Those paths join for
the oldest affected-line patch at T-00037 and ordered forward ports at T-00042. Operator skills, dispatcher
and runbook, and final CI traceability then close the epic through T-00038, T-00043, and T-00039.

The Fight Common compatibility path starts at T-00047. Scheduler, JSend, namespace, package, transactional
UnitOfWork, and private Mercure lanes then proceed through T-00048 to T-00054, T-00059, and T-00060. After
those slices and the release certification engine complete, T-00056 composes the Fight Common black-box
`1.2.0` compatibility evidence required before T-00017 can close release acceptance. Framework composition
evidence remains repository-owned and joins the umbrella through the T-00061 through T-00067 handoff graph.

The repository handoff path starts independently at T-00061. Once Fight AccessControl owns its canonical local
plan and bootstrap receipt, T-00062 through T-00066 may establish the five starter foundations in parallel.
T-00067 then verifies the six authority transfers and closes WF-018 without centralizing their builds, local
acceptance criteria, visibility decisions, or release state in Fight Common.

## Recently Closed

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
| [T-00055 — Retire the In-Repository Framework Fixture Plan](00055-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Closed `wontfix`; the five real starter repositories own framework dependency and compatibility evidence, so Fight Common will not regain nested Composer projects or a combined framework application. |

## Recently Done

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
| [T-00031 — Add the Tracked Pre-Commit Build Gate](00031-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added opt-in tracked pre-commit enforcement with repository-root resolution, disconnected stdin, exact default-build delegation, unchanged status propagation, documented activation and bypass, and no pre-push duplicate. |
| [T-00030 — Run Latest-Compatible Verification in CI](00030-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Replaced duplicated hosted quality commands with ephemeral latest-compatible resolution followed by direct shared-gate execution, preserving supported PR targets, database services, and explicit failure propagation. |
| [T-00029 — Deliver the Disposable Local Build and Dependency Modes](00029-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added one non-interactive local build that composes tracked or latest-compatible dependency resolution, invoking-user ownership, disposable databases, linked-worktree support, and the shared quality gate in one PHP container. |
| [T-00028 — Establish the Shared Executable Quality Gate](00028-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added one visibly ordered, fail-fast host-neutral gate with deterministic process coverage and current-invocation-only exact Clover enforcement for agent and CI-prepared environments. |
| [T-00016 — Document Event Sourcing Integration and Operations](00016-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Published the complete navigable lifecycle and operations guide with framework-free composition first, shipped Symfony mapping integration, migration and recovery contracts, and single-source executable aggregate, projection, and publication examples. |
| [T-00015 — Add Symfony Event-Mapping Autoconfiguration](00015-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Added container-tested provider auto-tagging and reference composition through the portable Event Mapper registration and validation path while preserving framework-free construction and exact coverage. |
| [T-00027 — Enforce Zero-Exclusion Exact Coverage](00027-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added a fail-closed production-directive and Clover-metric gate with deterministic process fixtures, exact 9,033/9,033 statement evidence, and no stale-report orchestration leakage from T-00028. |
| [T-00046 — Flatten the PHPCS Standard Implementation Layout](00046-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Flattened the unreleased ruleset, sniff filesystem, and PHP namespace; established `Phpcs.*` custom identifiers while retaining the `FightCommon` standard name and behavior. |
| [T-00024 — Make Scheduler Coverage Exact](00024-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed all ten Scheduler exclusions through deterministic runtime and lock controls while preserving public behavior, exact `ProcessRunner` outcomes, and complete coverage. |
| [T-00026 — Cover Process and FTP Integration Boundaries](00026-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed the process and FTP exclusions through real Symfony process execution and an isolated native-delegating FTP test boundary while preserving public contracts and exact complete coverage. |
| [T-00025 — Cover Adapter Failure Boundaries](00025-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed seven filesystem, template-buffering, and StatsD exclusions through deterministic test controls and an internal UDP sender while preserving public contracts and exact complete coverage. |
| [T-00023 — Eliminate Core Coverage Exclusions](00023-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed core iterator, timezone, and validation-service exclusions, covered public failure behavior, eliminated hidden test-order dependence, and retained exact complete statement coverage. |
| [T-00022 — Introduce Mandatory Architecture Enforcement](00022-TICKET.md) | [PRD-00008](../specs/00008-PRD.md) | Enforced exact runtime and Standards dependency allowances with mandatory violation and unassigned-token checks, repaired Scheduler through `ProcessBuilder` and required `ProcessRunner`, and retained exact complete coverage without baselines or skips. |
| [T-00014 — Persist and Log Publication Operational State](00014-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added monotonic DBAL publication cursors, transactional first-evidence failure recording, shared SQLite/MySQL/PostgreSQL conformance, and required-delegate PSR-3 logging while preserving exact coverage. |
| [T-00021 — Migrate and Enable Documentation Rules](00021-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Resolved 288 documentation findings across 124 production files, retained semantic and static-analysis contracts, enabled all documentation rules without suppressions, and preserved exact complete coverage. |
| [T-00020 — Migrate and Enable Member Layout Rules](00020-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Enabled four canonical member-layout rules, corrected 273 order and spacing violations without changing nonblank production content, aligned Rector ownership, and preserved exact complete coverage. |
| [T-00045 — Repair Mechanical PHPCBF Fixer Output](00045-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Corrected canonical strict-types whitespace and return indentation, proved second-pass PHPCBF idempotence, and preserved exact complete coverage. |
| [T-00019 — Migrate and Enable Mechanical Coding Rules](00019-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Enabled five canonical mechanical rules, corrected and semantically reviewed 105 production violations, preserved exact complete coverage, and captured two PHPCBF fixer regressions in T-00045. |
| [T-00018 — Package the Reusable FightCommon Coding Standard](00018-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Published the optional path-free standard, accepted Omphalos parity, stable compatibility contracts, dist-like consumer discovery, complete custom-sniff coverage, and copy-ready integration guidance. |
| [T-00044 — Eliminate Environment-Skipped Database Tests](00044-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added skip-free disposable MySQL/PostgreSQL verification, complete and fast PHPUnit workflows, scoped cleanup, CI fail-on-skipped enforcement, and exact Clover evidence. |
| [T-00013 — Publish Committed Events with In-Memory Operational State](00013-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added committed-event publication with monotonic named cursors, idempotent bounded failure records, explicit infrastructure-failure propagation, and crash-retry duplicate coverage. |
| [T-00012 — Isolate Synchronous Dispatcher Handler Failures](00012-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added complete two-phase handler fan-out with ordered transient failure aggregation and raw infrastructure-failure propagation across simple and service-aware dispatchers. |
| [T-00011 — Persist Projection Checkpoints with DBAL](00011-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added independently installable DBAL checkpoints with atomic monotonic saves, named reset, and shared lifecycle/concurrency conformance across SQLite, MySQL, and PostgreSQL. |
| [T-00010 — Run Projections with In-Memory Checkpoints](00010-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added stable projector contracts, ordered bounded projection with per-event checkpoints, fail-stop retry, crash-duplicate coverage, and isolated reset-to-zero rebuild behavior. |
| [T-00009 — Implement EventSourcedRepository](00009-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | Added stable aggregate definitions and a generic save/find repository with ordered reconstitution, expected-version appends, empty-save no-op, and fail-stop release behavior. |
| [T-00008 — Implement the Doctrine DBAL Event Store](00008-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | Added SQLite/MySQL/PostgreSQL DBAL storage with transactional sequence allocation, exact retry recovery, and CI-backed conformance verification. |
| [T-00007 — Implement the In-Memory Event Store](00007-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added atomic mapped append, exact retry classification, historical hydration, ordered polling, and the reusable Event Store conformance suite. |
| [T-00006 — Implement Event Mapping and Upcasting](00006-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added explicit direct/provider mapping registration, stable bidirectional event identity, validated sequential upcasting, and fail-closed current-event hydration. |
| [T-00005 — Define Stored-Event and Event Store Contracts](00005-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Defined stable stream identity, immutable stored-event envelopes, ordered Event Store operations, exact retries, optimistic concurrency, and prefix-stable global visibility. |
| [T-00004 — Implement Aggregate Lifecycle](00004-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added explicit record, replay, version, one-time release, fail-closed routing, and private-constructor reconstitution behavior through a framework-free aggregate contract. |
| [T-00003 — Isolate Metadata Across Message Envelopes](00003-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Made every command, query, and event envelope copy mutable metadata on construction and access while preserving same-ID derivation, serialization, and equality. |
| [T-00001 — Evaluate a Consumer Migration Pilot](00001-TICKET.md) | [PRD-00001](../specs/00001-PRD.md) | Selected Omphalos Metis `Operation` as the one bounded, non-release-blocking migration pilot after an equal-criteria comparison with Fight CMS and the Fight project template. |
| [T-00002 — Establish Event Sourcing Context and Decisions](00002-TICKET.md) | [PRD-00002](../specs/00002-PRD.md) | Established the ubiquitous language and durable architectural decisions that unblock both Event Sourcing implementation branches. |
