---
id: T-00003
prd: PRD-00003
title: Implement AggregateRoot lifecycle
status: ready-for-agent
blocked_by: T-00002
---

# Implement AggregateRoot Lifecycle

## Acceptance

- Aggregates record plain events through explicit `apply()` routing.
- Replay increments versions without re-recording.
- Releasing events returns them in order and clears pending state.
- Behavior has complete coverage.
