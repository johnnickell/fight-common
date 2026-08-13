---
id: T-00047
prd: PRD-00014
title: Establish the Public API Authority and Consumer Harness
status: ready-for-agent
blocked_by:
---

# Establish the Public API Authority and Consumer Harness

## What to Build

Establish the intentional `1.1.0` public API baseline and the black-box consumer harness used by every later
compatibility slice. A maintainer can classify the supported package surface, compare a candidate against the
authoritative release, run a representative installed-package probe, and receive stable findings without
treating generated inventory as policy.

## Acceptance Criteria

- [ ] The manifest identifies the authoritative published `1.1.0` tag and peeled commit rather than inferring a
      baseline from tag ordering.
- [ ] Completeness scanning accounts for 131 Domain declarations, 13 production-autoloaded Domain functions,
      166 Application declarations, and 107 Adapter declarations in the WF-014 planning baseline.
- [ ] Every declaration introduced after `1.1.0` is deliberately classified, and an unclassified production
      declaration or function fails validation.
- [ ] Callable, constructible, extensible, and implementable promises are recorded independently with reviewable
      evidence; non-final visibility alone does not establish an extension promise.
- [ ] Behavioral contracts and package-surface promises link to their normative authority and designated
      fixtures using stable identifiers.
- [ ] A generated inventory validates manifest completeness but cannot silently add, remove, or broaden a
      public promise.
- [ ] The consumer harness installs the candidate as a package, invokes only public APIs, and emits attributed
      machine-readable findings and an exact package-resolution receipt.
- [ ] Baseline drift, candidate drift, missing classification, unsupported checker output, and an indeterminate
      operation promise fail closed with stable finding IDs.

## Verification

Full submit gate, `./bin/planning-check`, manifest completeness and drift fixtures, baseline/candidate structural
comparison, and the first disposable installed-package consumer probe.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
