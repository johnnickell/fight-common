---
id: T-00036
prd: PRD-00012
title: Implement maintenance-line lifecycle decisions
status: wontfix
blocked_by:
---

# Implement Maintenance-Line Lifecycle Decisions

## Resolution

Closed by ADR 0025. Fight Common will not rebuild maintenance-line simulation, support-clock fakes, or a repository
release state machine. A real maintenance need requires a fresh scoped decision and human-authorized Git workflow.

## What to Build

Implement supported-line maintenance selection, support-policy transitions, EOL read-only behavior, and
the guided urgent journey on top of the certified release lifecycle.

## Acceptance Criteria

- [ ] Support-policy identity and exact line tips are bound into every maintenance plan.
- [ ] Maintenance and EOL transitions are explicit, durable, and auditable.
- [ ] EOL lines cannot receive new release effects.
- [ ] Urgency changes visibility and escalation only; it never bypasses ordinary gates.
- [ ] Unknown support-policy evidence stops before mutation.

## Verification

Full submit gate, planning validation, and deterministic support-clock and transition fixtures.

## Parent

PRD-00012 — Maintenance-Line and Patch Workflows.
