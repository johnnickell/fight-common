# ADR 0002: Checkpointed At-Least-Once Projections

- Status: accepted
- Date: 2026-08-01

## Decision

Projectors read the Event Store's global sequence in bounded batches. Each projector owns an independent checkpoint. A runner advances the checkpoint only after successful handling, producing at-least-once delivery. Projector writes must therefore be idempotent.

Fight Common supplies batch execution and checkpoint adapters. Consuming applications own commands, scheduling, process supervision, and read-database schemas.
