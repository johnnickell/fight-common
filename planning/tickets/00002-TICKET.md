---
id: T-00002
prd: PRD-00002
title: Establish Event Sourcing context and decisions
status: done
blocked_by:
---

# Establish Event Sourcing Context and Decisions

## Acceptance

- `CONTEXT.md` defines shared vocabulary and boundaries.
- ADRs record aggregate/storage and projection delivery decisions.
- Tracker and agent guidance distinguish durable planning from `.runs/` scratch.

## Outcome

Expanded the context from Event Sourcing terminology into the repository's existing ubiquitous language: architecture, domain primitives, CQRS messaging, persistence, validation, and supporting capabilities. Proposed 1.2 terminology is explicitly separated from current production APIs.
