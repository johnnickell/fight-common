---
id: T-00084
prd: PRD-00015
title: Publish the Complete Laravel Filesystem Adapter
status: done
blocked_by:
---

# Publish the Complete Laravel Filesystem Adapter

## Outcome

Replace Laravel's Symfony Filesystem fallback with a reusable Fight Common adapter that composes
`Illuminate\Filesystem\Filesystem`, completes the remaining Fight operations with PHP filesystem primitives,
and preserves the complete Fight exception and behavior contract.

## Scope

- In scope: the Laravel filesystem adapter, its capability-scoped service-provider registration, shared
  conformance evidence, optional-dependency authority, and the Laravel support-matrix correction.
- Out of scope: application path policy, remote file storage, Laravel disk configuration, other framework
  filesystem compositions, starter-repository changes, and release publication.

## Acceptance Criteria

- [x] `Fight\Common\Adapter\Filesystem\Laravel\LaravelFilesystem` satisfies the complete shared
      `Filesystem` conformance suite through its public Fight contract.
- [x] Matching operations delegate to `Illuminate\Filesystem\Filesystem`; missing touch, access-time,
      executable, ownership, and group operations use PHP filesystem primitives without leaking native failures.
- [x] Recursive mode and ownership operations, mirror deletion, absolute paths, symbolic links, and PHP include
      behavior preserve the existing Fight contract.
- [x] Laravel's `FilesystemServiceProvider` resolves the Laravel adapter and activates no unrelated capability.
- [x] Laravel remains an optional development/suggested dependency and Symfony Filesystem remains independently
      available for Symfony, Yii, CodeIgniter, Slim, and standalone compositions.
- [x] The normative support guide and PRD identify Laravel local filesystem support as shipped, not a fallback.

## Verification

- Focused Laravel filesystem conformance and booted provider tests, `./bin/planning-check`, and canonical
  `./bin/build` with exact statement coverage.

## Completion Notes

Completed on 2026-09-01. Published the complete Laravel filesystem adapter, registered it through the
capability-scoped provider, retained Laravel as optional and Symfony Filesystem as independently selectable, and
updated the public authority and framework-support records. Focused Laravel filesystem verification passed with
48 tests and 232 assertions plus exact 237/237 adapter statements. The final `./bin/planning-check` passed with
101 records and 24 active records. The canonical `./bin/build` passed with 4,055 tests, 15,250 assertions, and exact
18,566/18,566 statement coverage.
