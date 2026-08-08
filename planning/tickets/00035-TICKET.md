---
id: T-00035
prd: PRD-00011
title: Publish the signed tag and immutable GitHub release
status: ready-for-agent
blocked_by: T-00034
---

# Publish the Signed Tag and Immutable GitHub Release

## What to Build

Publish the exact certified candidate through separately authorized merge, signed-tag, push, and immutable
GitHub Release effects. Reconcile every possibly completed Git or GitHub effect independently after a crash
or ambiguous provider response and emit a durable handoff for downstream projection verification.

## Acceptance Criteria

- [ ] Merge, signed-tag creation, push, draft preparation, and public GitHub publication each require a
      separate authorization bound to the exact plan, candidate, version, manifest, and exceptions.
- [ ] The approved signer fingerprint, annotated tag object, peeled candidate commit, remote ref, release
      assets, and immutable GitHub state are verified as postconditions.
- [ ] Public publication stops unless immutable releases and the protected publication checkpoint are
      verified; a mutable fallback is rejected.
- [ ] A possibly completed effect enters `partial_publication`, preserves evidence, and is reconciled without
      deletion, tag reuse, force-push, version substitution, or blind retry.
- [ ] Verified already-satisfied postconditions resume idempotently and successful GitHub publication emits a
      durable downstream-verification handoff.

## Verification

Full submit gate and offline Git, signing, authorization, and GitHub effect-ledger tests for every success,
uncertainty, crash, and reconciliation branch.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.
