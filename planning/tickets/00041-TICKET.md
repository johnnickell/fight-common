---
id: T-00041
prd: PRD-00011
title: Verify Packagist Projection and Published Installation
status: ready-for-human
blocked_by: T-00035
---

# Verify Packagist Projection and Published Installation

## Outcome

After T-00035 publishes the certified release, verify Packagist's projection and install the exact public version in
a fresh `--no-dev` consumer. This is a bounded observation and qualification outcome, not a simulated provider
workflow.

## Acceptance Criteria

- [ ] Packagist reports the exact version and source reference published by T-00035.
- [ ] A new temporary consumer installs that exact public version with `--prefer-dist --no-dev`.
- [ ] The installed consumer completes the representative public behavior probe and cannot autoload
      `Fight\Release\`.
- [ ] Stale, missing, mismatched, timed-out, or failed evidence remains incomplete and is never reported as passed.
- [ ] Any Packagist-affecting recovery requires a separate explicit authorization.

## Verification

Capture the exact Packagist metadata and clean-install outcome, compare them with the T-00035 publication receipt and
T-00056 certification record, and record any mismatch without automatic mutation.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and the still-applicable Packagist boundary in ADR 0016.
