---
id: T-00039
prd: PRD-00013
title: Add runbook, CI integration, and final handoff validation
status: ready-for-agent
blocked_by: T-00038
---

# Add Runbook, CI Integration, and Final Handoff Validation

## What to Build

Add the dispatcher and journey-card runbook, integrate the deterministic verification surface with CI,
and validate final traceability from the epic through every executable ticket.

## Acceptance Criteria

- [ ] Journey cards cover feature, unreleased fix, patch, forward port, release, maintenance transition,
  and incomplete publication.
- [ ] Routing cannot select a workflow that contradicts repository or release state.
- [ ] Troubleshooting gives diagnosis, evidence, canonical recovery selection, and escalation ownership;
  it never gives blind retry instructions.
- [ ] CI invokes the canonical gate without duplicating release policy.
- [ ] All epic, PRD, ticket, acceptance, and verification links resolve and `./bin/planning-check` passes.

## Verification

Full submit gate, planning validation, link checks, and the complete deterministic offline journey suite.

## Parent

PRD-00013 — Operator Surfaces and Release Integration.
