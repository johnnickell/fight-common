---
id: T-00058
prd: PRD-00015
title: Publish the Framework Support Matrix and Activation Guide
status: done
blocked_by: T-00054
---

# Publish the Framework Support Matrix and Activation Guide

## What to Build

Publish one normative capability-by-framework guide derived from proven adapters and fallbacks. A Symfony,
Laravel, Yii, CodeIgniter, Slim, or standalone consumer can identify its supported line, select only the needed
capability integrations, install the exact optional packages, and understand which behavior is shipped, being
prototyped, wired through a standard/provider, or deliberately starter-owned.

## Acceptance Criteria

- [x] Documentation records the current-only supported constraints for Symfony `^8.1`, Laravel `^13.0`,
      CodeIgniter `^4.7`, Slim `^4.15`, and the accepted current Yii 3 package set.
- [x] Each framework documents its widen trigger, tighten trigger, PHP 8.6 re-resolution horizon, and the rule
      that the support window never exceeds two maintained majors.
- [x] Every audited capability is labeled `ship`, `prototype`, or `wire` for all five frameworks, with the exact
      adapter, standard, provider, or starter-owned policy named.
- [x] Capability-scoped Symfony compiler passes, Laravel providers, Yii providers/configuration groups,
      CodeIgniter service delegates, and Fight-container registrars are activated explicitly; no aggregate
      provider is documented.
- [x] Queue documentation covers complete Fight envelopes, neutral synchronous delegation, at-least-once
      delivery, post-commit submission where native, and starter-owned broker, retry, failure, worker, topology,
      and outbox policy.
- [x] Yii Queue is identified as unavailable for stable `1.2` support rather than skipped, with the stable
      upstream and production-broker evidence required for a possible additive `1.3` adapter.
- [x] Authentication documentation limits Fight Common to exact password, HMAC, JWT, and boolean authenticator
      seams and assigns guards, sessions, principals, and authorization to downstream project integration.
- [x] PSR documentation distinguishes direct interface use, lossless adapters, explicit mismatches, and standards
      with no Fight capability; PSR-6 cache is not confused with unrelated PSR contracts.
- [x] Composer suggestion entries and guide installation instructions agree with T-00054 receipts and do not add
      framework packages to production requirements.
- [x] Failed prototypes document the exact missing operation or value and the tested fallback; no capability is
      silently omitted from a support claim.
- [x] Additive adapters remain eligible for `1.3`, while legacy-name removal and incompatible inward changes are
      reserved for `2.0`.

## Verification

Full submit gate, `./bin/planning-check`, documentation review against ADRs 0020, 0023, and 0024, Composer
metadata audit, link validation, and a matrix-to-conformance traceability check.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-020 through WF-025 and ADR 0024.
