---
id: T-00026
prd: PRD-00009
title: Cover process and FTP integration boundaries
status: done
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

- [x] Symfony process execution success, output, failure, and retry paths execute through deterministic tests.
- [x] Process lifecycle behavior remains compatible with the Application-owned process contracts.
- [x] FTP transport behavior is exercised through an owned seam or focused deterministic integration
  boundary that requires no live FTP service in the normal build.
- [x] Boundary repairs preserve existing public APIs and operational error behavior.
- [x] Every production coverage-ignore directive in the process and FTP scope is removed.
- [x] The existing submit gate remains green with exact complete statement coverage for the measured source.

## Parent

PRD-00009 — Build, Dependency, and Coverage Verification.

## Outcome

Symfony process execution now exercises the real default factory for success, stdout, stderr, exit-code
failure, and output-disabled behavior while deterministic collaborators retain retry and cleanup control.
Output-disabled processes start without Symfony's prohibited callback, preserving the Application-owned
contracts and public API. FTP transport behavior executes through an isolated test-only namespace boundary
that delegates to native functions outside each test, covers connection and transport failures without a live
service, and leaves production signatures, native calls, and operational errors unchanged. All process and FTP
coverage exclusions are removed.

## Verification

- Rector, PHPStan, PHPCS, both mandatory Deptrac checks, planning validation, and `git diff --check` pass.
- The complete disposable-database suite passes 3,063 tests with 5,496 assertions and zero skips.
- Clover coverage is exact at 8,997/8,997 statements and 1,862/1,862 methods overall.
- `SymfonyProcessRunner` is exact at 153/153 statements and 14/14 methods.
- `FtpFileTransport` is exact at 119/119 statements and 10/10 methods.
- Independent Standards and Spec reviews report no findings.
