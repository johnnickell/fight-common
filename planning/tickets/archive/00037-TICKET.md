---
id: T-00037
prd: PRD-00012
title: Release the oldest affected supported-line patch
status: wontfix
blocked_by:
---

# Release the Oldest Affected Supported-Line Patch

## Resolution

Closed by ADR 0025. Patch selection and publication will not be simulated inside Fight Common. A concrete supported-
line repair must establish its own evidence, review, certification, and separately authorized publication path.

## What to Build

Consume one immutable reviewed fix, classify every supported line through focused behavioral evidence, and
release the patch from the exact tip of the oldest affected supported line. Carry the candidate through its
own compatibility classification, certification, publication, and verified downstream handoff.

## Acceptance Criteria

- [ ] Every supported line is classified affected, unaffected, or unknown before selection.
- [ ] Unknown classification stops before base-branch selection.
- [ ] The reviewed fix binds exact commit OIDs, review approvals, required-check conclusions, and merge
      provenance rather than a mutable branch or pull-request number.
- [ ] The oldest affected line uses its exact current tip as the patch base and stops on ref movement,
      unreleased maintenance content, or an expired support boundary.
- [ ] Patch eligibility is independently classified and any exception is bound to this candidate, version,
      baselines, findings, and evidence manifest.
- [ ] The patch is certified, published, and verified through the ordinary release gates; no `hotfix/*` or
      urgency path bypasses review, compatibility, evidence, or authorization.

## Verification

Full submit gate and offline reviewed-fix, affected-line, compatibility, publication, and EOL journey fixtures.

## Parent

PRD-00012 — Maintenance-Line and Patch Workflows.
