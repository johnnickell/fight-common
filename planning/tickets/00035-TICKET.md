---
id: T-00035
prd: PRD-00011
title: Publish the Certified Tag and GitHub Release
status: ready-for-human
blocked_by:
---

# Publish the Certified Tag and GitHub Release

## Outcome

After a separately authorized merge, fetch the exact remote `main` commit into a clean isolated release worktree,
freshly certify `1.2.0` there, and only then publish it through explicitly authorized, separately verified signed
tag, push, and immutable GitHub Release actions. This is a human-operated publication outcome, not another
repository release engine.

## Acceptance Criteria

- [ ] The approved release branch is merged to `main`; the exact remote merge is fetched cleanly and freshly
      certified with `./bin/release certify 1.2.0` before signing that same commit.
- [ ] Publication uses version `1.2.0` and a fresh exact-`main` certification whose commit, archive digest, and
      certification identity are verified after the T-00017-accepted product tree is merged.
- [ ] Merge, tag, push, and GitHub Release are separately authorized before each effect.
- [ ] The remote tag object, peeled commit, immutable GitHub Release, assets, and checksums are verified after the
      corresponding action; an uncertain postcondition stops for reconciliation rather than blind retry.
- [ ] The draft contains exactly the certified Composer tar, `certification.json`, and `SHA256SUMS`; immutable
      publication is separately confirmed only after those assets are verified.
- [ ] No repository command receives signing, GitHub, or publication credentials as part of certification.
- [ ] A concise publication receipt identifies the verified external objects for T-00041.

## Verification

Use `.runs/handoffs/t00035-publish-1.2.0.sh` to guide the approved effects. Review the newly created exact-main
certification record, perform each separately approved action with provider-native tools, and verify the exact
remote identities independently. This ticket itself authorizes no publication.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and ADR 0027.
