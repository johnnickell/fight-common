---
id: T-00054
prd: PRD-00014
title: Prove Optional Adapter Dependency Modes and Production Isolation
status: ready-for-agent
blocked_by: T-00050,T-00051,T-00052,T-00053,T-00057,T-00059,T-00060,T-00069,T-00070,T-00071,T-00073,T-00074
---

# Prove Optional Adapter Dependency Modes and Production Isolation

## What to Build

Extend the exported-package consumer harness across Fight Common's locked, lowest, latest, and production-only
dependency modes after the selected adapters are known. A maintainer receives exact receipts proving that
framework packages are development-only test dependencies and discoverable suggestions, while a production
Fight Common installation loads no unselected framework or provider.

## Acceptance Criteria

- [ ] Repository-locked, lowest-permitted, and latest-permitted dependency modes resolve independently and record
      exact package versions and lock digests.
- [ ] Symfony, Laravel, Yii, CodeIgniter, Slim, and optional provider packages required for adapter conformance are
      development requirements rather than Fight Common production requirements.
- [ ] Composer suggestions name the exact optional packages required by each shipped or documented prototype
      adapter without treating suggestion prose as a version constraint.
- [ ] One disposable exported-package consumer installs Fight Common with `--no-dev`, loads representative public
      APIs, and proves that no optional framework package is installed or eagerly referenced.
- [ ] Separate disposable consumers can install each selected framework adapter stack without installing any of
      the other four frameworks.
- [ ] Every dependency mode runs its applicable adapter, namespace, transaction, messaging, HTTP, cache, and
      package probes rather than treating dependency solving alone as success.
- [ ] Lowest resolution uses declared minimum stable constraints, and latest resolution starts from the package
      manifest rather than accidentally reusing the tracked lock.
- [ ] Exported-package verification compares production autoloading, package metadata, included content, archive
      identity, and development-only exclusions with the candidate authority.
- [ ] Missing, skipped, failed, drifted, or indeterminate evidence produces an attributed failed lane and exactly
      one resumable next action.
- [ ] Lane receipts are stable inputs to T-00056 and do not duplicate release-publication orchestration.

## Verification

Full submit gate, `./bin/planning-check`, disposable locked/lowest/latest resolutions, per-framework isolated
consumer installs, archive comparison, production `--no-dev` installation, and deterministic failure fixtures.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Sources

WF-024, ADR 0024, and the package-isolation decisions in PRD-00014 and PRD-00015.
