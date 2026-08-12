---
id: T-00020
prd: PRD-00007
title: Migrate and enable member layout rules
status: done
blocked_by: T-00019
---

# Migrate and Enable Member Layout Rules

## What to Build

Adopt the standard's member ordering and spacing rules across every named production type as the second green
migration batch. Preserve public behavior while making the canonical type layout enforceable without legacy
exceptions.

## Blocked By

T-00019 — Migrate and Enable Mechanical Coding Rules.

## Acceptance Criteria

- [x] Named production types use the canonical constant, property, constructor, method, and magic-member order.
- [x] Visibility groups and adjacent members use the canonical spacing.
- [x] Formatting changes preserve public APIs and behavior.
- [x] The member-layout rules are enabled with zero baseline or suppressed legacy violations.
- [x] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.

## Outcome

Enabled the four canonical member-layout rules across production source and corrected all 273 discovered
violations: 23 member-order findings across 19 files and 250 visibility-group spacing findings across 47 files.
The migration preserves every production file's nonblank content while relocating intact member blocks and
removing only forbidden blank separators. A targeted Rector exclusion assigns the overlapping class-member
spacing convention to the canonical FightCommon PHPCS rule so the two enforced tools cannot contradict each
other.

## Verification

- Focused member-layout scans pass, and a second focused PHPCBF pass makes no changes.
- Rector dry-run and PHPStan pass across 409 production files; root PHPCS passes with all four rules enabled.
- The complete disposable-database PHPUnit lifecycle passes 2,988 tests and 5,216 assertions with zero skips.
- Clover coverage is exact at 8,692/8,692 statements and 1,835/1,835 methods.
- Planning validation and `git diff --check` pass.
- Independent Standards and Spec reviews report no findings.
