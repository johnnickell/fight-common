---
id: T-00035
prd: PRD-00011
title: Prepare and Publish the 1.2.0 Git-Flow Release
status: done
blocked_by:
---

# Prepare and Publish the 1.2.0 Git-Flow Release

## Outcome

Record the published `v1.2.0` GitHub release and its immutable tag identity. This is a human-operated publication
outcome, not another repository release engine.

## Acceptance Criteria

- [x] `release/1.2.0` is cut from freshly fetched `origin/develop` in an isolated release worktree at the
      T-00017-accepted candidate `6047e7c1e321acfa627bc713bec0f5802dbe58e7`.
- [x] The release branch records this Git-flow route in the tracked planning contract, passes
      `./bin/planning-check` and `./bin/build` from its isolated worktree, and contains no unintended product or
      dependency changes.
- [x] GitHub reports the immutable, non-draft, non-prerelease `v1.2.0` release with an empty asset inventory.
- [x] GitHub reports the signed annotated `v1.2.0` tag object `8745e719afb2448c59d7c3f6d0e2f577b5e99bce`, verified
      by GitHub, peeling to commit `a2cd615d9b5064c9c30e994655536176249cd73b`.
- [x] The publication receipt for T-00041 identifies `v1.2.0`, the immutable zero-asset GitHub Release, and
      `a2cd615d9b5064c9c30e994655536176249cd73b` as the verified downstream comparison identity.

## Verification

Provider-native GitHub inspection verified the immutable release, zero assets, annotated tag object, GitHub
signature verification, and peeled commit above. ADR 0027 accepts the observed zero-asset inventory; this ticket
does not claim unobserved certification, merge, signing-custody, or authorization evidence.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and ADR 0027.
