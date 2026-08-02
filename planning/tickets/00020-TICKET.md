---
id: T-00020
prd: PRD-00007
title: Migrate and enable member layout rules
status: ready-for-agent
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

- [ ] Named production types use the canonical constant, property, constructor, method, and magic-member order.
- [ ] Visibility groups and adjacent members use the canonical spacing.
- [ ] Formatting changes preserve public APIs and behavior.
- [ ] The member-layout rules are enabled with zero baseline or suppressed legacy violations.
- [ ] The complete submit gate remains green with exact complete coverage.

## Parent

PRD-00007 — Reusable Fight Coding Standard.
