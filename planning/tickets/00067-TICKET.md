---
id: T-00067
prd: PRD-00016
title: Verify All Six Repository Handoffs and Close WF-018
status: ready-for-agent
blocked_by: T-00061,T-00062,T-00063,T-00064,T-00065,T-00066
---

# Verify All Six Repository Handoffs and Close WF-018

## What to Build

Verify that Fight AccessControl and all five framework starters have accepted canonical local implementation
authority at immutable private commits, and close the umbrella Wayfinder without turning Fight Common into a
cross-repository build or duplicate tracker. Leave implementation, visibility, and release readiness with each
owning repository.

## Acceptance Criteria

- [ ] T-00061 through T-00066 are terminal with one canonical repository-plan link and one immutable successful
      bootstrap receipt for each repository.
- [ ] Fight AccessControl is the canonical owner of PRD-00017 behavior and its repository-local capability graph.
- [ ] Symfony, Laravel, Yii, CodeIgniter, and Slim are each canonical owners of their PRD-00018 product,
      framework-composition, walking-slice, documentation, and release graphs.
- [ ] Cross-repository blocking edges express AccessControl-first bootstrap and the shared-contract gates for
      later slice packets without requiring synchronized local status in Fight Common.
- [ ] Every repository remains independently buildable from a clean clone and records its own build and hosted
      gate as the authoritative completion evidence.
- [ ] The umbrella links local plans and immutable receipts but copies no repository-local acceptance criteria,
      implementation status, framework build output, or release state.
- [ ] The portability map, EPIC-00004, PRD-00016, and WF-018 agree on ownership, dependency order, and the completed
      authority transfer.
- [ ] WF-018 closes without waiting for production capability implementation, public visibility, stable-branch
      promotion, version tags, Packagist publication, template enablement, or create-project distribution.
- [ ] Public visibility and every installable-release effect remain explicit repository-owned approvals rather
      than consequences of this umbrella verification.

## Verification

Fight Common `./bin/planning-check`, read-only verification of all six canonical local-plan links and immutable
commit receipts, blocker-graph validation, and a duplication audit across the umbrella artifacts. No central
cross-repository build or publication action is required.

## Parent

PRD-00016 — Fight Package and Starter Repository Ownership.
