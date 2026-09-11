---
id: T-00057
prd: PRD-00015
title: Pin Fight Common's Symfony Components to the Current Supported Line
status: done
blocked_by:
---

# Pin Fight Common's Symfony Components to the Current Supported Line

## What to Build

Align Fight Common's own `require-dev` Symfony constraints with the supported-line decision: every Symfony
component pins the current `^8.1` floor (`symfony/process` moves from `^7.0`, the rest from `^8.0`), the
tracked lock re-resolves at both lowest and latest, and Fight Common's own root dependency lane proves it
resolves Symfony 8.1 exactly as the current-only support window specifies.

## Acceptance Criteria

- [x] Every `symfony/*` `require-dev` entry uses the `^8.1` floor and `symfony/process` no longer allows a
      Symfony 7 line.
- [x] Lowest and latest dependency resolutions complete with exact versions and lock digests, and the
      tracked lock reflects the approved constraints.
- [x] Fight Common's root dependency lane resolves Symfony 8.1, with no dependency forcing a `^7.2` or `^7.4` Symfony
      floor into the current-only window.
- [x] The full submit gate passes under both tracked and latest-compatible resolution without new
      exclusions or waivers.
- [x] Documentation records that Fight Common pins the current `^8.1` line and that the widened
      `^8.2 || ^8.1` form is adopted only when Symfony 8.2 ships (≈Nov 2026).
- [x] No framework or optional adapter package is added to production requirements by this change.

## Verification

Full submit gate, `./bin/planning-check`, lowest and latest root resolution, and an audit that the
declared constraints and lock receipts match the ADR 0020 supported-line table.

Implemented by `b455e5dcb5c9bda36977e8ccf947f9c0f68973a2`; the tracked constraints, lock, documentation,
and both dependency modes remain enforced by the canonical build. This status repair aligns the ticket with its
existing Recently Done Board entry and EPIC-00004 progress record.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.
