---
id: T-00019
prd: PRD-00007
title: Migrate and enable mechanical coding rules
status: ready-for-agent
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

- [ ] Production imports are alphabetically ordered.
- [ ] Strict types, array comma and alignment conventions, and blank lines before non-trivial returns match the
  published standard.
- [ ] Automatically fixable changes are reviewed for semantic preservation before acceptance.
- [ ] The migrated rules are enabled with zero baseline or suppressed legacy violations.
- [ ] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.
