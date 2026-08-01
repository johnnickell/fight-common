---
id: T-00002
prd: PRD-00002
title: Establish Event Sourcing context and decisions
status: done
blocked_by:
---

# Establish Event Sourcing Context and Decisions

## What to Build

Establish the shared Event Sourcing vocabulary, aggregate and storage boundaries, projection and publication guarantees, and durable planning conventions that every later ticket uses.

## Blocked By

None — can start immediately.

## Acceptance

- [x] `CONTEXT.md` defines shared vocabulary and boundaries.
- [x] ADRs record aggregate/storage and projection delivery decisions.
- [x] Projection and publication are defined as separate processes with different progress and failure guarantees.
- [x] Tracker and agent guidance distinguish durable planning from `.runs/` scratch.
- [x] Downstream PRDs and tickets use the accepted terms consistently.

## Outcome

Expanded the context from Event Sourcing terminology into the repository's existing ubiquitous language: architecture, domain primitives, CQRS messaging, persistence, validation, and supporting capabilities. Proposed 1.2 terminology is explicitly separated from current production APIs.
