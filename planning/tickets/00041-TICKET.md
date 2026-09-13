---
id: T-00041
prd: PRD-00011
title: Verify Packagist Projection and Published Installation
status: done
blocked_by:
---

# Verify Packagist Projection and Published Installation

## Outcome

After T-00035 publishes the certified release, verify Packagist's projection and install the exact public version in
a fresh `--no-dev` consumer. This is a bounded observation and qualification outcome, not a simulated provider
workflow.

## Acceptance Criteria

- [x] Packagist reports `v1.2.0` with both dist and source reference
      `a2cd615d9b5064c9c30e994655536176249cd73b`, matching T-00035's verified tag identity.
- [x] A new temporary consumer installed exactly `johnnickell/fight-common:v1.2.0` with
      `composer install --prefer-dist --no-dev --no-interaction`.
- [x] The installed consumer completed the representative UUID and typed-collection public behavior probe and
      could not autoload `Fight\Release\Application\CertificationRecord`.
- [x] Packagist observation and the installation result matched; no Packagist recovery or provider mutation occurred.

## Verification

Packagist's `v1.2.0` dist and source references both resolved to `a2cd615d9b5064c9c30e994655536176249cd73b`.
The fresh no-dev consumer lock recorded the same dist and source references. The installed-package probe passed:
UUID round trip `6ba7b810-9dad-11d1-80b4-00c04fd430c8`, typed collection count `1`, and no release namespace.

## Parent

PRD-00011 — Release Lifecycle and Publication Recovery.

## Decision Source

ADR 0025 and ADR 0027.
