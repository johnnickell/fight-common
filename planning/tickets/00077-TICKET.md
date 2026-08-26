---
id: T-00077
prd: PRD-00014
title: Publish the Canonical Doctrine Transactional UnitOfWork Adapter
status: done
blocked_by: T-00059
---

# Publish the Canonical Doctrine Transactional UnitOfWork Adapter

## What to Build

Publish `Fight\\Common\\Adapter\\Persistence\\Doctrine\\DoctrineTransactionalUnitOfWork` as the canonical Doctrine
adapter for the narrow `TransactionalUnitOfWork` contract. Keep the legacy `UnitOfWork` and
`Adapter\\Repository\\DoctrineUnitOfWork` journeys functional throughout `1.x`, but make every retained use of
`UnitOfWork::commit()` explicit legacy/deprecated behavior rather than the canonical persistence path.

## Acceptance Criteria

- [x] `DoctrineTransactionalUnitOfWork` lives under `Adapter\\Persistence\\Doctrine`, implements only
      `TransactionalUnitOfWork`, delegates successful work to Doctrine's transaction boundary, preserves callback
      results and failure propagation, reports close state, and rejects nested entry consistently.
- [x] The canonical adapter exposes no artificial standalone `commit()` operation.
- [x] `Adapter\\Repository\\DoctrineUnitOfWork`, the legacy `UnitOfWork` interface, and their `commit()` journey
      remain functional through `1.x` without runtime deprecation notices.
- [x] Every production, documentation, fixture, and installed-consumer reference that invokes
      `UnitOfWork::commit()` or `DoctrineUnitOfWork::commit()` is either migrated to the narrow transactional
      journey or deliberately retained and marked as a deprecated legacy example or compatibility probe.
- [x] Existing Doctrine repository, data-type, and UnitOfWork documentation names
      `Adapter\\Persistence\\Doctrine\\DoctrineTransactionalUnitOfWork` as canonical and identifies the old
      Repository-path adapter as 1.x compatibility only.
- [x] Public API manifest, compatibility authority, installed-package probes, and deprecation evidence classify
      the canonical class and every retained legacy public surface deliberately.
- [x] `CHANGELOG.md` records the canonical transactional Doctrine adapter as added and the legacy
      `UnitOfWork::commit()` / Repository-path adapter journey as deprecated for `1.x` without claiming removal.
- [x] Full submit gate, `./bin/planning-check`, focused Doctrine conformance tests, legacy and narrow installed
      consumer probes, and exact coverage pass.

## Exclusions

- Removing `UnitOfWork`, `DoctrineUnitOfWork`, or `commit()` before `2.0.0`.
- Adding framework-native Laravel, Yii, or CodeIgniter adapters; their tickets consume the narrow contract.
- Changing Doctrine's persistence or transaction semantics beyond the established portable nested-entry policy.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
