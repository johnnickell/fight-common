---
id: T-00059
prd: PRD-00014
title: Publish the Additive Transactional UnitOfWork Boundary
status: done
blocked_by: T-00047
---

# Publish the Additive Transactional UnitOfWork Boundary

## What to Build

Publish the narrower transactional UnitOfWork journey without breaking the legacy `1.x` contract. Application
code can depend only on transactional execution and lifecycle state, existing consumers retain standalone
`commit()`, and Doctrine plus representative native-record adapters prove the same portable transaction
semantics without pretending their persistence mechanisms are identical.

## Acceptance Criteria

- [x] `TransactionalUnitOfWork` exposes `commitTransactional()` and `isClosed()` without requiring the legacy
      standalone `commit()` operation.
- [x] The existing `UnitOfWork` extends the narrower contract, retains functional `commit()` behavior throughout
      `1.x`, and documents that operation as deprecated without emitting a runtime notice.
- [x] A consumer adapter can implement only `TransactionalUnitOfWork` and pass the designated conformance suite
      without adding an artificial standalone commit operation.
- [x] Transactional execution preserves the callback result and atomically commits the complete mutation plus
      every required same-store audit write.
- [x] Callback failure rolls back the complete transaction, propagates the original failure, and preserves the
      established terminal lifecycle behavior.
- [x] Nested transactional execution fails explicitly and consistently instead of exposing savepoint or false
      transaction behavior through the portable contract.
- [x] The Doctrine adapter satisfies both the narrower and legacy contracts while preserving existing commit,
      rollback, close-state, and exception behavior.
- [x] Installed-package probes compile and run unchanged legacy consumers and new narrower-contract consumers
      using only public package APIs.
- [x] The public API manifest and compatibility findings classify the new contract, the legacy extension
      relationship, and every retained operation deliberately.

## Verification

Full submit gate, `./bin/planning-check`, focused transaction and Doctrine tests, representative native-record
conformance adapters, nested-call failure tests, and installed-package legacy and narrower-contract probes.

Verified by `./bin/planning-check` and `./bin/build`: 3,709 tests, 13,416 assertions, and exact 17,168/17,168
statement coverage. The linked-worktree build harness now mounts its source and Git metadata safely, so the
installed-package compatibility journey runs under the canonical quality gate.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
