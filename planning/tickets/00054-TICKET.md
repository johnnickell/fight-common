---
id: T-00054
prd: PRD-00014
title: Prove Root Dependency Modes and Production Package Isolation
status: ready-for-agent
blocked_by: T-00047
---

# Prove Root Dependency Modes and Production Package Isolation

## What to Build

Extend the consumer harness with Fight Common's repository-locked, lowest-permitted, latest-permitted,
exported-package, and production-only installation journeys. A maintainer receives exact dependency and package
receipts while optional frameworks remain outside production requirements and framework-specific choices remain
reserved for WF-015.

## Acceptance Criteria

- [ ] Repository-locked, lowest-permitted, and latest-permitted dependency modes resolve independently and
      record exact package versions and lock digests.
- [ ] Every mode runs the complete applicable quality and compatibility probes rather than treating dependency
      solving alone as success.
- [ ] Lowest resolution uses the declared minimum constraints with stable preference and does not ignore
      platform or security requirements.
- [ ] Latest resolution starts from the package manifest rather than reusing the tracked lock accidentally.
- [ ] Exported-package verification compares production autoloading, package metadata, included content, and
      archive identity with the candidate authority.
- [ ] A disposable consumer installs the exported candidate with `--no-dev`, loads representative public APIs,
      and proves no optional framework package is required or eagerly loaded.
- [ ] Known optional adapter packages are discoverable through package suggestions and documentation without
      turning suggestion text into a version constraint or inventing WF-015 framework choices.
- [ ] Missing, skipped, failed, drifted, or indeterminate resolution and package evidence produces an attributed
      failed lane and one resumable next action.
- [ ] Lane results expose the stable receipt contract consumed by T-00034 without duplicating that ticket's
      release-certification orchestration.

## Verification

Full submit gate, `./bin/planning-check`, disposable locked/lowest/latest resolutions, archive comparison,
production `--no-dev` installation, optional-package absence probes, and deterministic failure fixtures.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
