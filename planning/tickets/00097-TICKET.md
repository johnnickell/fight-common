---
id: T-00097
prd: PRD-00021
title: Complete Operations and Framework Guidance
status: ready-for-agent
blocked_by: T-00094
---

# Complete Operations and Framework Guidance

## Outcome

Complete the capability atlas with trustworthy Operate Workloads and Integrate Frameworks guidance, separating
portable component behavior from supported activation, provider selection, framework-native composition, and
maintainer-facing operational practice.

## Scope

- In scope: observability, processes, scheduling, framework support, framework-specific composition, maintenance
  routes, atlas metadata, optional dependencies, failures, operations, relationships, and next steps.
- Out of scope: starter-repository implementation, unsupported framework promises, duplicating component
  semantics, changing support policy, or publishing the GitHub-profile adaptation.

## Acceptance Criteria

- [ ] Observability, processes, and scheduling each have canonical guides with accurate ownership, dependencies,
      portable usage, available adapters, operational behavior, failures, and next steps.
- [ ] Integrate Frameworks exposes the supported framework and framework-free paths directly from the atlas.
- [ ] Framework guidance owns activation, supported integrations, provider selection, native composition, starter
      routes, and known unavailability without copying each component's semantics.
- [ ] Support claims and optional-package requirements agree with the normative compatibility and framework
      guidance already owned by the repository.
- [ ] Maintenance and contributor routes keep coding standards, development setup, and delivery practice outside
      the product component taxonomy.
- [ ] Public symbols, examples, commands, repository links, configuration syntax, and behavior claims are current.
- [ ] Search, navigation, atlas links, anchors, copy controls, code scrolling, callouts, and both themes work for
      the completed routes.
- [ ] Representative process, scheduler, observability, and framework-composition checks prove copied behavior
      without creating a cross-framework browser or application matrix.

## Verification

- Build the generated routes and validate atlas, navigation, search, links, anchors, public symbols, dependencies,
  and support claims.
- Run a focused representative set of existing process, scheduler, observability, and composition checks.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Pending T-00094.
