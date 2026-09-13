---
id: T-00019
prd: PRD-00007
title: Migrate and enable mechanical coding rules
status: done
blocked_by: T-00018
---

# Migrate and Enable Mechanical Coding Rules

## What to Build

Adopt the published standard's mechanical rules across production source as one green migration batch. Apply
safe automatic corrections, review their semantics, repair remaining violations, and enable the migrated
rules without leaving a compatibility baseline.

## Blocked By

T-00018 — Package the Reusable FightCommon Coding Standard.

## Acceptance Criteria

- [x] Production imports are alphabetically ordered.
- [x] Strict types, array comma and alignment conventions, and blank lines before non-trivial returns match the
  published standard.
- [x] Automatically fixable changes are reviewed for semantic preservation before acceptance.
- [x] The migrated rules are enabled with zero baseline or suppressed legacy violations.
- [x] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.

## Outcome

Enabled the five canonical mechanical rules across production source and corrected all 105 discovered
violations: 41 import-ordering findings, 26 trailing array commas, 35 array-arrow alignments, one missing
strict-types declaration, and two missing return separators. Automatic corrections were reviewed for
semantic preservation, and no baseline or suppression remains for the migrated rules.

The migration exposed malformed output from the strict-types and blank-line-before-return PHPCBF fixers.
The affected production edits were normalized manually without behavior changes, and T-00045 now owns the
separate regression-first repair of those reusable fixers.

## Verification

- Rector dry-run and PHPStan pass across 409 production files.
- Root PHPCS passes with all five mechanical rules enabled.
- The complete disposable-database PHPUnit suite passes 2,987 tests and 5,206 assertions with zero skips.
- Clover coverage is exact at 8,692/8,692 statements and 1,835/1,835 methods.
- Planning validation passes with 61 records and 41 active tickets; `git diff --check` is clean.
- Independent two-axis review reports no actionable Standards finding and no Spec finding.
