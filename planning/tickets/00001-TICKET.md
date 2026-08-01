---
id: T-00001
prd: PRD-00001
title: Evaluate a consumer migration pilot
status: ready-for-human
blocked_by:
---

# Evaluate a Consumer Migration Pilot

## What to Build

Compare Fight CMS, the current Fight project template, and Omphalos against the accepted Event Sourcing contracts. Deliver one evidence-backed pilot recommendation with a bounded aggregate, migration sequence, rollback boundary, operational risks, and explicit exclusions. Consumer implementation remains in its owning repository, and this evaluation does not block 1.2.

## Blocked By

None — can start immediately.

## Acceptance

- [ ] Candidate seams and current event flows are evidence-backed.
- [ ] All three candidates are assessed against the same accepted contracts.
- [ ] One pilot is recommended with reasons, a migration sequence, a rollback boundary, risks, and explicit exclusions.
- [ ] Unknowns are identified rather than inferred as absent behavior.
- [ ] Consumer changes remain in their owning repository.
- [ ] The recommendation remains non-release-blocking for Fight Common 1.2.
