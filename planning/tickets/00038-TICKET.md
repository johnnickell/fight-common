---
id: T-00038
prd: PRD-00013
title: Add release skills and catalog routing
status: ready-for-agent
blocked_by: T-00035,T-00037
---

# Add Release Skills and Catalog Routing

## What to Build

Add the six thin `.agents` release skills and catalog entries. Each skill owns one phase, invokes only
allowlisted `bin/release` commands, links to canonical planning, and exposes approvals, postconditions,
stops, and one next action.

## Acceptance Criteria

- [ ] Six skills route without copying policy or silently invoking the next phase.
- [ ] Capability boundaries reject cross-phase effects before execution.
- [ ] Catalog links resolve to the canonical epic, PRDs, tickets, and runbook.
- [ ] Operator output identifies bound plan/run IDs, evidence, approvals, and next action.
- [ ] Offline routing fixtures cover normal, patch, urgent, EOL, and incomplete-publication cases.

## Verification

Full submit gate, planning validation, and catalog/link checks.

## Parent

PRD-00013 — Operator Surfaces and Release Integration.
