---
id: T-00025
prd: PRD-00009
title: Cover adapter failure boundaries
status: ready-for-agent
blocked_by:
---

# Cover Adapter Failure Boundaries

## What to Build

Make filesystem, PHP template buffering, and StatsD failure behavior deterministically observable without
changing their public contracts. Execute each defensive branch through an owned collaborator, controlled
runtime condition, or behavior-preserving boundary repair and remove the associated coverage exceptions.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [ ] Filesystem rename and link failure outcomes are exercised without depending on host-specific accidents.
- [ ] PHP template rendering covers inactive or lost output-buffer conditions through deterministic behavior.
- [ ] Metrics connection failure behavior is executed without requiring a live StatsD service.
- [ ] Exceptions, return behavior, and cleanup remain compatible with the existing public contracts.
- [ ] Every production coverage-ignore directive in this ticket's Adapter scope is removed.
- [ ] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
