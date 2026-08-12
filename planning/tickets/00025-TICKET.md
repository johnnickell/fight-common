---
id: T-00025
prd: PRD-00009
title: Cover adapter failure boundaries
status: done
blocked_by:
---

# Cover Adapter Failure Boundaries

## What to Build

Make filesystem, PHP template buffering, and StatsD failure behavior deterministically observable without
changing their public contracts. Execute each defensive branch through an owned collaborator, controlled
runtime condition, or behavior-preserving boundary repair and remove the associated coverage exceptions.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [x] Filesystem rename and link failure outcomes are exercised without depending on host-specific accidents.
- [x] PHP template rendering covers inactive or lost output-buffer conditions through deterministic behavior.
- [x] Metrics connection failure behavior is executed without requiring a live StatsD service.
- [x] Exceptions, return behavior, and cleanup remain compatible with the existing public contracts.
- [x] Every production coverage-ignore directive in this ticket's Adapter scope is removed.
- [x] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Filesystem metadata and MIME failures now execute through path-scoped test-only function controls while the
existing Symfony rename and symlink exception translations remain intact. PHP template rendering tracks the
output buffers it owns and reports a lost buffer without consuming caller-owned buffers. The default StatsD
path delegates to an internal UDP sender whose socket-creation failure is deterministic and remains a silent
no-op. All seven ticket-scoped Adapter coverage exclusions are removed without changing public signatures or
production filesystem semantics.

## Verification

- Rector, PHPStan, PHPCS, both mandatory Deptrac checks, planning validation, and `git diff --check` pass.
- The complete disposable-database suite passes 3,034 tests with zero skips.
- Clover coverage is exact at 8,851/8,851 statements and 1,851/1,851 methods overall.
- `SymfonyFilesystem`, `PhpEngine`, `StatsDMetricsCollector`, and `UdpMetricSender` each have exact method and
  statement coverage.
- Independent Standards review reports no blocking findings; independent Spec review reports no findings.
