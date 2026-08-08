---
id: T-00041
prd: PRD-00011
title: Verify Packagist projection and clean installation
status: ready-for-agent
blocked_by: T-00035
---

# Verify Packagist Projection and Clean Installation

## What to Build

Complete a GitHub-published release by observing its bounded Packagist projection, installing the exact
version in a clean temporary Composer consumer, and reconciling downstream incompleteness without weakening
the signed tag and GitHub Release authority.

## Acceptance Criteria

- [ ] Packagist observation follows the approved bounded polling schedule and compares the exact version,
      source reference, distribution metadata, and expected package projection.
- [ ] Timeout, stale metadata, mismatched source or distribution, and installation failure emit
      `packagist_incomplete` with preserved evidence and one next action.
- [ ] Clean-install proof uses the exact published version without development dependencies and verifies
      production autoloading plus the approved representative public-API probe.
- [ ] Recovery revalidates each downstream postcondition, requires separate authorization for any
      Packagist-affecting effect, and never treats metadata presence or provider acknowledgement as authority.
- [ ] Successful completion emits a permanent clean-install receipt linked to the immutable evidence manifest,
      with bounded, redacted, digest-linked supporting logs.

## Verification

Full submit gate and deterministic clock, Packagist response-sequence, temporary-consumer, clean-install,
timeout, mismatch, crash, and recovery fixtures.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.
