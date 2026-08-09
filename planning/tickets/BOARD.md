# Ticket Board

Operational execution view for Fight Common. Ticket files are canonical for status and blocking edges; this
board is canonical for recommended order. IDs identify artifacts only. Update this file whenever ticket
status, dependencies, or roadmap priority changes.

Last updated: 2026-08-09

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
| 1 | [T-00013 — Publish Committed Events with In-Memory Operational State](00013-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Builds publication execution on the completed synchronous failure-isolation contract. |
| 2 | [T-00018 — Package the Reusable FightCommon Coding Standard](00018-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Opens the reusable quality-gate chain and the staged internal migration. |
| 3 | [T-00022 — Introduce Mandatory Architecture Enforcement](00022-TICKET.md) | [PRD-00008](../specs/00008-PRD.md) | Proves the inward dependency boundary independently of the coding-standard migration. |
| 4 | [T-00023 — Eliminate Core Coverage Exclusions](00023-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Revalidates core coverage workarounds and covers deterministic Domain and Application failures. |
| 5 | [T-00025 — Cover Adapter Failure Boundaries](00025-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Opens deterministic coverage of filesystem, template-buffering, and metrics failures. |
| 6 | [T-00026 — Cover Process and FTP Integration Boundaries](00026-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Replaces live-infrastructure exclusions with owned deterministic seams. |
| 7 | [T-00015 — Add Symfony Event-Mapping Autoconfiguration](00015-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Reuses the completed portable provider-registration path without blocking the Event Store implementation chain. |
| 8 | [T-00032 — Establish Release Inspection, Plans, and Boundary Fakes](00032-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | Opens the read-only inspection and immutable planning journey after the Wayfinder handoff. |

## Waiting

All tickets retain `ready-for-agent`; their position here is derived from unfinished blocking edges.

| Suggested Order | Ticket | Parent PRD | Waiting On |
|-----------------|--------|------------|------------|
| 9 | [T-00014 — Persist and Log Publication Operational State](00014-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | T-00013 |
| 10 | [T-00016 — Document Event Sourcing Integration and Operations](00016-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | T-00014 |
| 11 | [T-00017 — Complete 1.2 Compatibility and Release Acceptance](00017-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | T-00016 |
| 12 | [T-00019 — Migrate and Enable Mechanical Coding Rules](00019-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00018 |
| 13 | [T-00020 — Migrate and Enable Member Layout Rules](00020-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00019 |
| 14 | [T-00021 — Migrate and Enable Documentation Rules](00021-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00020 |
| 15 | [T-00024 — Make Scheduler Coverage Exact](00024-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00022 |
| 16 | [T-00027 — Enforce Zero-Exclusion Exact Coverage](00027-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00023, T-00024, T-00025, T-00026 |
| 17 | [T-00028 — Establish the Shared Executable Quality Gate](00028-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00021, T-00022, T-00027 |
| 18 | [T-00029 — Deliver the Disposable Local Build and Dependency Modes](00029-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00028 |
| 19 | [T-00030 — Run Latest-Compatible Verification in CI](00030-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00028 |
| 20 | [T-00031 — Add the Tracked Pre-Commit Build Gate](00031-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00029 |
| 21 | [T-00040 — Prove Resumable Release Runs and Phase Handoffs](00040-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | T-00032 |
| 22 | [T-00033 — Prove the Normal Feature Package Journey](00033-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00040 |
| 23 | [T-00034 — Compose Certification Evidence and Compatibility Lanes](00034-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00033 |
| 24 | [T-00035 — Publish the Signed Tag and Immutable GitHub Release](00035-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00034 |
| 25 | [T-00041 — Verify Packagist Projection and Clean Installation](00041-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00035 |
| 26 | [T-00036 — Implement Maintenance-Line Lifecycle Decisions](00036-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00033, T-00034 |
| 27 | [T-00037 — Release the Oldest Affected Supported-Line Patch](00037-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00036, T-00041 |
| 28 | [T-00042 — Forward-Port Patches Through Newer Affected Lines](00042-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | T-00037 |
| 29 | [T-00038 — Add Release Skills and Catalog Routing](00038-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00041, T-00042 |
| 30 | [T-00043 — Add the State-First Dispatcher and Journey-Card Runbook](00043-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00038 |
| 31 | [T-00039 — Integrate CI and Validate the Final Epic Handoff](00039-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | T-00043 |

The completed projection path now combines ordered at-least-once handling and fail-stop retry with durable,
named monotonic DBAL checkpoints proven on SQLite, MySQL, and PostgreSQL. Synchronous dispatcher failure isolation
is complete, making in-memory event publication the next ready branch while durable publication state remains behind it. The quality-gate
paths also open independently: the coding-standard migration advances sequentially through
T-00018 to T-00021, architecture enforcement proceeds through T-00022, and independent coverage migrations
can begin at T-00023, T-00025, and T-00026. Scheduler coverage follows the architecture repair at T-00024.
All four coverage slices join at T-00027; T-00028 then establishes the shared gate, after which local build
and CI delivery split into T-00029 and T-00030. T-00031 attaches pre-commit enforcement to the completed local
build.

The release path starts at T-00032 and T-00040, then proves packaging and certification through T-00033 and
T-00034. GitHub publication continues through T-00035 and downstream verification through T-00041, while
maintenance lifecycle work may proceed independently at T-00036 after certification. Those paths join for
the oldest affected-line patch at T-00037 and ordered forward ports at T-00042. Operator skills, dispatcher
and runbook, and final CI traceability then close the epic through T-00038, T-00043, and T-00039.

## Recently Done

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
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
