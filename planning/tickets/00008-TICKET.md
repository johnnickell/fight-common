---
id: T-00008
prd: PRD-00004
title: Implement EventSourcedRepository
status: ready-for-agent
blocked_by: T-00003,T-00006
---

# Implement EventSourcedRepository

## Acceptance

- Repository reconstitutes aggregates from stored events.
- Save appends released events using the loaded expected version.
- Saving an aggregate without new events is a no-op.
