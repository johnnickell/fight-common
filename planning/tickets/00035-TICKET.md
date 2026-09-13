---
id: T-00035
prd: PRD-00011
title: Prepare and Publish the 1.2.0 Git-Flow Release
status: in-progress
blocked_by:
---

# Prepare and Publish the 1.2.0 Git-Flow Release

## Outcome

Cut `release/1.2.0` from freshly fetched `origin/develop`, validate the release candidate in its isolated
worktree, and merge that reviewed branch to `main` only through a separately authorized pull request. Fetch the
exact resulting remote `main` commit into a clean detached worktree, freshly certify `1.2.0` there, and only then
perform the explicitly authorized, separately verified signed-tag, push, and immutable GitHub Release actions.
This is a human-operated Git-flow release outcome, not another repository release engine.

## Acceptance Criteria

- [x] `release/1.2.0` is cut from freshly fetched `origin/develop` in an isolated release worktree at the
      T-00017-accepted candidate `6047e7c1e321acfa627bc713bec0f5802dbe58e7`.
- [x] The release branch records this Git-flow route in the tracked planning contract, passes
      `./bin/planning-check` and `./bin/build` from its isolated worktree, and contains no unintended product or
      dependency changes.
- [ ] The reviewed `release/1.2.0` pull request is separately authorized and merged to `main`; the exact remote
      merge is fetched cleanly and freshly certified with `./bin/release certify 1.2.0` before signing that same
      commit.
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

Use this tracked ticket as the durable release contract. Review the release-branch diff, run
`./bin/planning-check` and `./bin/build` in its isolated worktree, then obtain separate authorization to push the
branch and open its pull request to `main`. After that pull request is separately authorized and merged, review the
new exact-main certification record, perform each separately approved publication action with provider-native tools,
and verify the exact remote identities independently. This ticket itself authorizes no external effect.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and ADR 0027.
