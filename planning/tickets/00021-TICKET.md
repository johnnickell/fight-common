---
id: T-00021
prd: PRD-00007
title: Migrate and enable documentation rules
status: done
blocked_by: T-00020
---

# Migrate and Enable Documentation Rules

## What to Build

Complete the staged standard adoption by bringing production documentation into the canonical grammar.
Review declarations semantically, retain accurate inherited and static-analysis refinements, and enable the
documentation rules without suppressions.

## Blocked By

T-00020 — Migrate and Enable Member Layout Rules.

## Acceptance Criteria

- [x] Named production declarations follow the canonical type and method documentation grammar.
- [x] Documentation describes actual semantics and is not added mechanically when domain meaning requires
  judgment.
- [x] Inherited documentation and PHPStan-specific type refinements remain accurate.
- [x] Documentation rules are enabled with zero baseline or suppressed legacy violations.
- [x] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.

## Outcome

Enabled the canonical type, method, and function type-hint documentation rules after resolving all 288
findings across 124 production files. Canonical type headers retain meaningful semantic prose, inherited
contracts remain bare where no local refinement is required, and explicit PHPStan annotations preserve
generic and class-string constraints. Token-level review found no executable PHP changes.

## Verification

- Rector, PHPStan, root PHPCS, planning validation, and `git diff --check` pass.
- The complete disposable-database PHPUnit lifecycle passes 2,988 tests and 5,216 assertions with zero skips.
- Clover coverage is exact at 8,692/8,692 statements and 1,835/1,835 methods.
- Independent Standards and Spec reviews report no blocking findings.
