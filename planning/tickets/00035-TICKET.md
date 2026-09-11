---
id: T-00035
prd: PRD-00011
title: Publish the Certified Tag and GitHub Release
status: ready-for-human
blocked_by: T-00017
---

# Publish the Certified Tag and GitHub Release

## Outcome

Publish the exact candidate accepted by T-00017 through explicitly authorized, separately verified merge,
annotated signed tag, push, and GitHub Release actions. This is a human-operated publication outcome, not another
repository release engine.

## Acceptance Criteria

- [ ] Publication uses the exact version, commit, archive digest, and certification identity accepted by T-00017
      after T-00102 supersedes the earlier candidate.
- [ ] Merge, tag, push, and GitHub Release are separately authorized before each effect.
- [ ] The remote tag object, peeled commit, immutable GitHub Release, assets, and checksums are verified after the
      corresponding action; an uncertain postcondition stops for reconciliation rather than blind retry.
- [ ] No repository command receives signing, GitHub, or publication credentials as part of certification.
- [ ] A concise publication receipt identifies the verified external objects for T-00041.

## Verification

Review the current certification record accepted by T-00017, perform each approved publication action with the provider-native tool,
and verify the exact remote identities independently. This ticket itself authorizes no publication.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and the still-applicable publication boundary in ADR 0016.
