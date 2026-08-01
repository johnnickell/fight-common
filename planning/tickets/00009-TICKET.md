---
id: T-00009
prd: PRD-00005
title: Implement projector contracts and runner
status: ready-for-agent
blocked_by: T-00006
---

# Implement Projector Contracts and Runner

## Acceptance

- Each projector has a stable name and handles stored events explicitly.
- Runner polls bounded batches after the projector checkpoint.
- Checkpoints advance only after successful handling.
