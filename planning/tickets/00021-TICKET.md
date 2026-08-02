---
id: T-00021
prd: PRD-00007
title: Migrate and enable documentation rules
status: ready-for-agent
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

- [ ] Named production declarations follow the canonical type and method documentation grammar.
- [ ] Documentation describes actual semantics and is not added mechanically when domain meaning requires
  judgment.
- [ ] Inherited documentation and PHPStan-specific type refinements remain accurate.
- [ ] Documentation rules are enabled with zero baseline or suppressed legacy violations.
- [ ] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.
