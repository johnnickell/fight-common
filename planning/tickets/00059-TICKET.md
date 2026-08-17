---
id: T-00059
prd: PRD-00014
title: Publish the Additive Transactional UnitOfWork Boundary
status: ready-for-agent
blocked_by: T-00047
---

# Publish the Additive Transactional UnitOfWork Boundary

## What to Build

Publish the narrower transactional UnitOfWork journey without breaking the legacy `1.x` contract. Application
code can depend only on transactional execution and lifecycle state, existing consumers retain standalone
`commit()`, and Doctrine plus representative native-record adapters prove the same portable transaction
semantics without pretending their persistence mechanisms are identical.

## Acceptance Criteria

- [ ] `TransactionalUnitOfWork` exposes `commitTransactional()` and `isClosed()` without requiring the legacy
      standalone `commit()` operation.
- [ ] The existing `UnitOfWork` extends the narrower contract, retains functional `commit()` behavior throughout
      `1.x`, and documents that operation as deprecated without emitting a runtime notice.
- [ ] A consumer adapter can implement only `TransactionalUnitOfWork` and pass the designated conformance suite
      without adding an artificial standalone commit operation.
- [ ] Transactional execution preserves the callback result and atomically commits the complete mutation plus
      every required same-store audit write.
- [ ] Callback failure rolls back the complete transaction, propagates the original failure, and preserves the
      established terminal lifecycle behavior.
- [ ] Nested transactional execution fails explicitly and consistently instead of exposing savepoint or false
      transaction behavior through the portable contract.
- [ ] The Doctrine adapter satisfies both the narrower and legacy contracts while preserving existing commit,
      rollback, close-state, and exception behavior.
- [ ] Installed-package probes compile and run unchanged legacy consumers and new narrower-contract consumers
      using only public package APIs.
- [ ] The public API manifest and compatibility findings classify the new contract, the legacy extension
      relationship, and every retained operation deliberately.

## Verification

Full submit gate, `./bin/planning-check`, focused transaction and Doctrine tests, representative native-record
conformance adapters, nested-call failure tests, and installed-package legacy and narrower-contract probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
