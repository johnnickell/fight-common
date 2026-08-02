---
id: T-00026
prd: PRD-00009
title: Cover process and FTP integration boundaries
status: ready-for-agent
blocked_by:
---

# Cover Process and FTP Integration Boundaries

## What to Build

Replace live-process and live-FTP coverage exclusions with deterministic integration boundaries. Consumers
receive the same process lifecycle, failure, retry, and FTP transport behavior while the normal test suite
can execute every maintained statement without unavailable external infrastructure.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [ ] Symfony process execution success, output, failure, and retry paths execute through deterministic tests.
- [ ] Process lifecycle behavior remains compatible with the Application-owned process contracts.
- [ ] FTP transport behavior is exercised through an owned seam or focused deterministic integration
  boundary that requires no live FTP service in the normal build.
- [ ] Boundary repairs preserve existing public APIs and operational error behavior.
- [ ] Every production coverage-ignore directive in the process and FTP scope is removed.
- [ ] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.
