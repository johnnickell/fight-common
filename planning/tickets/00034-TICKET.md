---
id: T-00034
prd: PRD-00011
title: Compose certification evidence and compatibility lanes
status: ready-for-agent
blocked_by: T-00033
---

# Compose Certification Evidence and Compatibility Lanes

## What to Build

Implement verification-only certification that composes the complete quality, dependency, archive,
planning/API, compatibility, and Git/ref evidence lanes into one immutable manifest.

## Acceptance Criteria

- [ ] Locked, lowest, and latest dependency lanes are distinct and attributed.
- [ ] Public API, behavioral, Composer, environment, and compatibility evidence is classified.
- [ ] Failed or indeterminate lanes produce a durable certification stop.
- [ ] A hosted check or raw log cannot replace the composed manifest.
- [ ] The manifest digest binds the candidate, baselines, version, and approvals.

## Verification

Full submit gate, compatibility fixtures, archive/install checks, and deterministic failure fixtures.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.
