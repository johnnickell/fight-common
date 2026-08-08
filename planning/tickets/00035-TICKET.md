---
id: T-00035
prd: PRD-00011
title: Implement immutable publication and partial-publication recovery
status: ready-for-agent
blocked_by: T-00034
---

# Implement Immutable Publication and Partial-Publication Recovery

## What to Build

Implement separately authorized Git, signed-tag, GitHub Release, Packagist observation, clean-install,
and incomplete-publication recovery effects using the boundary ports and fakes from T-00032.

## Acceptance Criteria

- [ ] Each external effect has a separate authorization bound to the exact plan and manifest.
- [ ] Signed tag, peeled commit, GitHub release, approved assets, and immutable state are reverified.
- [ ] Packagist observation uses the bounded window and emits `packagist_incomplete` on timeout or mismatch.
- [ ] Recovery reconciles possible effects independently and never blindly retries.
- [ ] Successful publication emits durable provenance and clean-install receipts.

## Verification

Full submit gate and offline effect-ledger tests for every success, uncertainty, and recovery branch.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.
