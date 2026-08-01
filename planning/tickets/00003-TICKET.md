---
id: T-00003
prd: PRD-00003
title: Isolate metadata across message envelopes
status: ready-for-agent
blocked_by: T-00002
---

# Isolate Metadata Across Message Envelopes

## What to Build

Make every existing command, query, and event message an effective metadata boundary. Consumers can inspect or derive enriched same-ID envelopes without mutating the metadata snapshot already held by another message.

## Blocked By

- T-00002 — Establish Event Sourcing context and decisions.

## Acceptance

- [ ] Every command, query, and event message copies `Meta` at construction and returns a copy from `meta()`.
- [ ] `withMeta()` and `mergeMeta()` create derived envelopes with the same `MessageId` and a new isolated metadata snapshot.
- [ ] Standalone `Meta` remains mutable, while mutation through a message getter cannot alter the message.
- [ ] Message serialization and equality continue to reflect the envelope's isolated snapshot and identity.
- [ ] Existing public method signatures remain compatible.
- [ ] The behavioral change is identified for the 1.2 release notes.
- [ ] All envelope behavior has complete coverage.
