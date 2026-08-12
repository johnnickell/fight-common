---
id: T-00043
prd: PRD-00013
title: Add the state-first dispatcher and journey-card runbook
status: ready-for-agent
blocked_by: T-00038
---

# Add the State-First Dispatcher and Journey-Card Runbook

## What to Build

Route an operator from verified repository and release state to exactly one supported journey, then document
that journey through a common card that links the thin skill, bounded commands, approvals, postconditions,
stops, escalation owner, and next handoff without duplicating policy.

## Acceptance Criteria

- [ ] Routing classifies repository and release state before intent, affected supported lines, urgency
      metadata, and release class; an operator choice cannot contradict verified state.
- [ ] Journey cards cover feature, unreleased fix, supported-line patch, forward port, minor or major release,
      maintenance transition, and incomplete publication through one common structure.
- [ ] Unknown affected-line evidence, EOL, stopped runs, and partial publication route to their canonical stop
      or reconciliation journey before any effect.
- [ ] Troubleshooting is limited to diagnosis, evidence, canonical recovery selection, escalation ownership,
      and one next action; it never prescribes blind retry or destructive cleanup.
- [ ] Cancellation preserves run evidence and material input changes supersede the old plan and run rather
      than rewriting them.
- [ ] Dispatcher and runbook contract tests resolve every command, skill, planning, and handoff link without
      copying normative release policy.

## Verification

Full submit gate, planning validation, routing contradiction fixtures, journey-card contract and link checks,
cancellation and supersession tests, and offline stop and reconciliation journeys.

## Parent

PRD-00013 — Operator Surfaces and Release Integration.
