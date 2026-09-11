---
id: T-00097
prd: PRD-00021
title: Complete Operations and Framework Guidance
status: done
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

- [x] Observability, processes, and scheduling each have canonical guides with accurate ownership, dependencies,
      portable usage, available adapters, operational behavior, failures, and next steps.
- [x] Integrate Frameworks exposes the supported framework and framework-free paths directly from the atlas.
- [x] Framework guidance owns activation, supported integrations, provider selection, native composition, starter
      routes, and known unavailability without copying each component's semantics.
- [x] Support claims and optional-package requirements agree with the normative compatibility and framework
      guidance already owned by the repository.
- [x] Maintenance and contributor routes keep coding standards, development setup, and delivery practice outside
      the product component taxonomy.
- [x] Public symbols, examples, commands, repository links, configuration syntax, and behavior claims are current.
- [x] Search, navigation, atlas links, anchors, copy controls, code scrolling, callouts, and both themes work for
      the completed routes.
- [x] Representative process, scheduler, observability, and framework-composition checks prove copied behavior
      without creating a cross-framework browser or application matrix.

## Verification

- Build the routes with the strict MkDocs renderer and inspect atlas, navigation, search, links, anchors, public
  symbols, dependencies, and support claims directly in the rendered site.
- Check examples against current source and existing production-code tests. Do not add documentation,
  generated-file, fixture, or tooling tests.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`; PHPUnit remains limited to
  production code.

## Completion Notes

Completed the three Operate Workloads adoption guides, direct framework-free and five-framework paths, the
normative framework overview, and current maintainer guidance without changing PHP runtime behavior or support
policy. Framework pages now use honest navigation and breadcrumbs while the existing component article contract
remains unchanged.

Source and specification review corrected audit-repository symbols, structured logging semantics, metric tag
names, Scheduler ownership and `ext-posix` requirements, hook policy, dependency-lock prerequisites, and
maintenance navigation before acceptance. The strict documentation artifact, representative production tests,
responsive light/dark browser review, planning integrity, diff hygiene, and the canonical build completed
successfully. No documentation, generated-file, wrapper, configuration, or tooling tests were added.
