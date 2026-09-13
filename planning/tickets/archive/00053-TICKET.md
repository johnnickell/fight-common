---
id: T-00053
prd: PRD-00014
title: Publish Canonical Doctrine Data Type Paths
status: done
blocked_by: T-00047
---

# Publish Canonical Doctrine Data Type Paths

## What to Build

Publish capability-first Persistence/Doctrine/Type paths for all thirteen existing top-level Doctrine data
types while preserving their old public names. A Doctrine consumer can register either name and obtain the same
database conversion, PHP value, SQL declaration, and type identity behavior throughout `1.x`.

## Acceptance Criteria

- [x] Canonical paths exist for the audit-entry ID, email address, JSON object, multibyte string object,
      multibyte text, message, metadata, string object, string text, generic type, URI, URL, and UUID data types.
- [x] Every old Doctrine FQCN remains independently loadable, functional, and documented as deprecated through
      `1.x`.
- [x] Each old/new pair is tested for construction, database-to-PHP conversion, PHP-to-database conversion, SQL
      declaration, binding type, null behavior, invalid input, and platform-specific behavior where applicable.
- [x] Doctrine registration probes cover type names, registry identity, schema discovery, and round trips for
      every old/new pair.
- [x] Compatibility mechanisms are chosen per type from identity and registration evidence; a pure alias is
      accepted only when it preserves the complete consumer contract.
- [x] Existing serialized and persisted representations remain readable and writable without migration solely
      because of the PHP namespace expansion.
- [x] No runtime deprecation warning is emitted and no old type path is removed before `2.0.0`.
- [x] All twenty-six public identities and designated conversion behaviors are linked to stable compatibility
      findings.

## Verification

Full submit gate, `./bin/planning-check`, shared Doctrine type conformance tests, SQLite/MySQL/PostgreSQL round
trips where designated, registry and schema probes, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
