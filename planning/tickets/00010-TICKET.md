---
id: T-00010
prd: PRD-00005
title: Implement projection checkpoint stores
status: ready-for-agent
blocked_by: T-00007,T-00009
---

# Implement Projection Checkpoint Stores

## Acceptance

- In-memory and DBAL stores load, save, and reset independent checkpoints.
- Checkpoints never move backwards.
- Rebuild behavior is documented and covered.
