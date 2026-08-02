# Ticket Board

Operational execution view for Fight Common. Ticket files are canonical for status and blocking edges; this
board is canonical for recommended order. IDs identify artifacts only. Update this file whenever ticket
status, dependencies, or roadmap priority changes.

Last updated: 2026-08-01

## “What’s Next?” Contract

When `/ask-matt` or a plain “What’s next?” is invoked:

1. **Human decision:** return the item under **Now** when it still requires judgment.
2. **Implementation:** return the first ticket under **Ready Frontier**.
3. If the question is unqualified, return both targets. Never choose by ticket number alone.

## Now

| Track | Ticket | Parent PRD | Why Now |
|-------|--------|------------|---------|
| Human decision | [T-00001 — Evaluate a Consumer Migration Pilot](00001-TICKET.md) | [PRD-00001](../specs/00001-PRD.md) | Selects the real consumer and migration evidence needed to validate the additive 1.2 release. |

## Ready Frontier

These tickets have no unfinished blockers. Work top to bottom unless current context makes another ready
ticket materially cheaper.

| Rank | Ticket | Parent PRD | Why Next |
|------|--------|------------|----------|
| 1 | [T-00003 — Isolate Metadata Across Message Envelopes](00003-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Opens the Event Sourcing message-envelope chain and unblocks the stored-event contract. |
| 2 | [T-00004 — Implement Aggregate Lifecycle](00004-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Establishes the independent aggregate seam required by the repository path. |
| 3 | [T-00012 — Isolate Synchronous Dispatcher Handler Failures](00012-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Opens publication failure isolation independently of Event Store implementation. |
| 4 | [T-00018 — Package the Reusable FightCommon Coding Standard](00018-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Opens the reusable quality-gate chain and the staged internal migration. |
| 5 | [T-00022 — Introduce Mandatory Architecture Enforcement](00022-TICKET.md) | [PRD-00008](../specs/00008-PRD.md) | Proves the inward dependency boundary independently of the coding-standard migration. |
| 6 | [T-00023 — Eliminate Core Coverage Exclusions](00023-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Revalidates core coverage workarounds and covers deterministic Domain and Application failures. |
| 7 | [T-00025 — Cover Adapter Failure Boundaries](00025-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Opens deterministic coverage of filesystem, template-buffering, and metrics failures. |
| 8 | [T-00026 — Cover Process and FTP Integration Boundaries](00026-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Replaces live-infrastructure exclusions with owned deterministic seams. |

## Waiting

All tickets retain `ready-for-agent`; their position here is derived from unfinished blocking edges.

| Suggested Order | Ticket | Parent PRD | Waiting On |
|-----------------|--------|------------|------------|
| 9 | [T-00005 — Define Stored-Event and Event Store Contracts](00005-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | T-00003 |
| 10 | [T-00006 — Implement Event Mapping and Upcasting](00006-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | T-00005 |
| 11 | [T-00007 — Implement the In-Memory Event Store](00007-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | T-00006 |
| 12 | [T-00008 — Implement the Doctrine DBAL Event Store](00008-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | T-00007 |
| 13 | [T-00009 — Implement EventSourcedRepository](00009-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | T-00004, T-00007 |
| 14 | [T-00010 — Run Projections with In-Memory Checkpoints](00010-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | T-00007 |
| 15 | [T-00011 — Persist Projection Checkpoints with DBAL](00011-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | T-00008, T-00010 |
| 16 | [T-00013 — Publish Committed Events with In-Memory Operational State](00013-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | T-00007, T-00012 |
| 17 | [T-00014 — Persist and Log Publication Operational State](00014-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | T-00008, T-00013 |
| 18 | [T-00015 — Add Symfony Event-Mapping Autoconfiguration](00015-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | T-00006 |
| 19 | [T-00016 — Document Event Sourcing Integration and Operations](00016-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | T-00009, T-00011, T-00014 |
| 20 | [T-00017 — Complete 1.2 Compatibility and Release Acceptance](00017-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | T-00016 |
| 21 | [T-00019 — Migrate and Enable Mechanical Coding Rules](00019-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00018 |
| 22 | [T-00020 — Migrate and Enable Member Layout Rules](00020-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00019 |
| 23 | [T-00021 — Migrate and Enable Documentation Rules](00021-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | T-00020 |
| 24 | [T-00024 — Make Scheduler Coverage Exact](00024-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00022 |
| 25 | [T-00027 — Enforce Zero-Exclusion Exact Coverage](00027-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00023, T-00024, T-00025, T-00026 |
| 26 | [T-00028 — Establish the Shared Executable Quality Gate](00028-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00021, T-00022, T-00027 |
| 27 | [T-00029 — Deliver the Disposable Local Build and Dependency Modes](00029-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00028 |
| 28 | [T-00030 — Run Latest-Compatible Verification in CI](00030-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00028 |
| 29 | [T-00031 — Add the Tracked Pre-Commit Build Gate](00031-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | T-00029 |

The Event Sourcing paths split after the completed vocabulary contract: aggregate lifecycle and message
metadata can proceed independently, while dispatcher failure isolation opens the publication branch. The
quality-gate paths also open independently: the coding-standard migration advances sequentially through
T-00018 to T-00021, architecture enforcement proceeds through T-00022, and independent coverage migrations
can begin at T-00023, T-00025, and T-00026. Scheduler coverage follows the architecture repair at T-00024.
All four coverage slices join at T-00027; T-00028 then establishes the shared gate, after which local build
and CI delivery split into T-00029 and T-00030. T-00031 attaches pre-commit enforcement to the completed local
build.

## Recently Done

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
| [T-00002 — Establish Event Sourcing Context and Decisions](00002-TICKET.md) | [PRD-00002](../specs/00002-PRD.md) | Established the ubiquitous language and durable architectural decisions that unblock both Event Sourcing implementation branches. |
