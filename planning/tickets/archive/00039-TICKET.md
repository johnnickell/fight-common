---
id: T-00039
prd: PRD-00013
title: Integrate CI and validate the final epic handoff
status: wontfix
blocked_by:
---

# Integrate CI and Validate the Final Epic Handoff

## Resolution

Closed by ADR 0025. Expensive certification remains an on-demand release operation; everyday hosted CI continues to
run the product quality gate and does not reproduce certification or simulated provider state.

## What to Build

Integrate the deterministic verification surface with CI and prove the complete implementation handoff from
EPIC-00003 through every PRD, executable ticket, operator journey, command result, and acceptance artifact.

## Acceptance Criteria

- [ ] CI invokes the canonical gate without duplicating release policy.
- [ ] Hosted permissions, artifact transport, and evidence ingestion preserve the command's capability and
      machine-result contracts.
- [ ] Queued, running, skipped, cancelled, missing, and failed hosted checks cannot satisfy a composed lane.
- [ ] Catalog, skill, command, journey-card, ADR, PRD, ticket, acceptance, and verification links resolve.
- [ ] Traceability covers T-00032 through T-00043 with no unresolved or contradictory dependency or evidence
      edge, and `./bin/planning-check` passes.

## Verification

Full submit gate, planning validation, CI contract and hosted-status fixtures, link checks, and the complete
deterministic offline journey suite.

## Parent

PRD-00013 — Operator Surfaces and Release Integration.
