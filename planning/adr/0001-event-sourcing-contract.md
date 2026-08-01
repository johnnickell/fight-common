# ADR 0001: Minimal Optional Event Sourcing Contract

- Status: accepted
- Date: 2026-08-01

## Decision

Fight Common 1.2 adds Event Sourcing without replacing CQRS. Aggregates record plain domain events, explicitly apply them through aggregate-owned routing, and release pending events. Event stores append with an expected version and expose both stream-order and global-order reads.

Stored events use stable aliases and integer schema versions. A simple ordered upcaster chain may transform one stored payload into one newer payload. Doctrine DBAL remains optional; in-memory implementations define executable contract behavior.

## Excluded

Snapshots, sagas, split/merge upcasting, event deletion, multiple stream layouts, and framework-specific worker daemons are outside 1.2.
