---
id: T-00037
prd: PRD-00012
title: Implement affected-line patching and ordered forward ports
status: ready-for-agent
blocked_by: T-00035,T-00036
---

# Implement Affected-Line Patching and Ordered Forward Ports

## What to Build

Implement patch-line routing from affected-line evidence, oldest-supported-line-first execution, and
separate reviewed and certified forward ports through newer affected lines.

## Acceptance Criteria

- [ ] Every supported line is classified affected, unaffected, or unknown before selection.
- [ ] Unknown classification stops before base-branch selection.
- [ ] The oldest affected line uses its exact current tip as the patch base.
- [ ] Each forward port carries predecessor provenance and its own certification evidence.
- [ ] No `hotfix/*` or urgency path bypasses review, compatibility, or authorization.

## Verification

Full submit gate and offline multi-line fixtures covering affected, unaffected, unknown, conflict, and EOL cases.

## Parent

PRD-00012 — Maintenance-Line and Patch Workflows.
