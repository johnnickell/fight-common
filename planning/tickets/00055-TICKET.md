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
clean-clone evidence to the five initially private starter repositories that own those implementations.

## Decision Handoff

PRD-00015 still supplies the exact supported package lines and default compositions. T-00057 applies Fight
Common's own Symfony floor, and T-00058 publishes the normative support and activation guidance. PRD-00016
owns repository bootstrap and authority transfer; PRD-00018 owns repository-local compatibility and walking-
slice acceptance. A future umbrella handoff ticket may link immutable repository-owned receipts, but Fight
Common does not recreate or rerun their projects.

## Closure Evidence

- The nested WF-017 prototype projects were removed after their bounded evidence was retained.
- PRD-00016 requires six independent repository-owned clean-clone receipts and transfers detailed planning
  authority to each owning repository.
- PRD-00018 explicitly rejects five nested applications and one central cross-repository build in Fight
  Common.
- The PRD-00015 implementation graph therefore contains only T-00057 and T-00058 in Fight Common.

## Parent

PRD-00015 — Framework Supported Lines and Default Capability Compositions.
