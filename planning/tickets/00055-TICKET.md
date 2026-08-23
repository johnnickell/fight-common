---
id: T-00055
prd: PRD-00015
title: Retire the In-Repository Framework Fixture Plan
status: wontfix
blocked_by:
---

# Retire the In-Repository Framework Fixture Plan

## Resolution

Do not add five nested Composer projects or a combined framework project to Fight Common. The approved
repository boundary assigns framework dependency resolution, native composition, functional behavior, and
clean-clone evidence to the five public-source starter repositories that own those implementations.

## Decision Handoff

PRD-00015 still supplies the exact supported package lines and default compositions. T-00057 applies Fight
Common's own Symfony floor, and T-00058 publishes the normative support and activation guidance. PRD-00016
owns repository bootstrap and authority transfer; PRD-00018 owns repository-local compatibility and walking-
slice acceptance. T-00075 links immutable repository-owned receipts into the Fight Common certification graph,
but Fight Common does not recreate or rerun their projects.

## Closure Evidence

- The nested WF-017 prototype projects were removed after their bounded evidence was retained.
- PRD-00016 requires six independent repository-owned clean-clone receipts and transfers detailed planning
  authority to each owning repository.
- PRD-00018 explicitly rejects five nested applications and one central cross-repository build in Fight
  Common.
- The expanded PRD-00015 graph retains repository-owned booted journeys while T-00057, T-00058, and T-00070
  through T-00075 deliver Fight Common adapters, guidance, and receipt composition without nested applications.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.
