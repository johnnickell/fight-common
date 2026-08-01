---
id: T-00011
prd: PRD-00005
title: Implement the Event Dispatcher bridge
status: ready-for-agent
blocked_by: T-00009,T-00010
---

# Implement the Event Dispatcher Bridge

## Acceptance

- Stored messages are dispatched through the existing Event Dispatcher.
- The bridge uses the same checkpointed retry semantics as other projectors.
