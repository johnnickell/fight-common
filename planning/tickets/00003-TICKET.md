---
id: T-00003
prd: PRD-00003
title: Isolate metadata across message envelopes
status: done
blocked_by: T-00002
---

# Isolate Metadata Across Message Envelopes

## What to Build

Make every existing command, query, and event message an effective metadata boundary. Consumers can inspect or derive enriched same-ID envelopes without mutating the metadata snapshot already held by another message.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [x] Every command, query, and event message copies `Meta` at construction and returns a copy from `meta()`.
- [x] `withMeta()` and `mergeMeta()` create derived envelopes with the same `MessageId` and a new isolated metadata snapshot.
- [x] Standalone `Meta` remains mutable, while mutation through a message getter cannot alter the message.
- [x] Message serialization and equality continue to reflect the envelope's isolated snapshot and identity.
- [x] Existing public method signatures remain compatible.
- [x] The behavioral change is identified for the 1.2 release notes.
- [x] All envelope behavior has complete coverage.

## Outcome

Centralized metadata isolation in `BaseMessage`, so command, query, and event envelopes copy `Meta` when
constructed and return a copy when inspected. Same-ID derivation, serialization snapshots, equality, and
standalone `Meta` mutability remain compatible, with the intentional getter behavior correction recorded for
the 1.2 release.
