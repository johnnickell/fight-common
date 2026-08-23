---
id: T-00033
prd: PRD-00011
title: Prove the normal feature package journey
status: done
blocked_by: T-00068
---

# Prove the Normal Feature Package Journey

## What to Build

Implement plan/package behavior for a normal `develop` feature, including the exact approved local
effect set, candidate OID, deterministic rootless archive, and package handoff.

## Acceptance Criteria

- [x] A feature targeting `develop` routes to the normal release journey.
- [x] Packaging requires approval for the exact bounded local effect set.
- [x] The candidate OID and archive digest are bound into the handoff.
- [x] Archive ordering, timestamps, exclusions, and name are deterministic.
- [x] Offline tests prove approval, refusal, drift, and already-satisfied postconditions.

## Verification

Full submit gate, planning validation, archive reproducibility, and clean temporary installation.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.
