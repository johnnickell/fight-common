---
id: T-00046
prd: PRD-00007
title: Flatten the PHPCS standard implementation layout
status: done
blocked_by: T-00018
---

# Flatten the PHPCS Standard Implementation Layout

## What to Build

Before the reusable coding standard ships in Fight Common 1.2, remove the redundant `FightCommon` segment
from its PHP implementation namespaces and filesystem layout. Keep `FightCommon` as the public PHPCS standard
name while making `src/Standards/Phpcs/ruleset.xml` the one documented consumer entry point.

## Blocked By

T-00018 — Package the Reusable FightCommon Coding Standard.

## Acceptance Criteria

- [x] The canonical ruleset lives at `src/Standards/Phpcs/ruleset.xml`, with no forwarding ruleset at the old
  unreleased path.
- [x] Custom sniffs and their supporting helper live under `src/Standards/Phpcs/Sniffs` in the
  `Fight\Common\Standards\Phpcs\Sniffs` namespace without a redundant `FightCommon` segment.
- [x] The ruleset remains named `FightCommon` and exposes the ten custom sniffs through their pre-release
  `Phpcs.*` identifiers while preserving diagnostic codes, configurable properties, built-in rules, and default
  behavior.
- [x] Fight Common's root configuration, focused tests, dist-like consumer fixtures, and public documentation
  use the flattened ruleset path.
- [x] Composer autoloading and consumer-owned scan paths, exclusions, individual sniff selection, and property
  overrides continue to work without a Composer installer plugin.
- [x] The complete non-interactive submit gate remains green with exact complete coverage.

## Out of Scope

- Renaming the `FightCommon` PHPCS standard or any diagnostic code.
- Adding compatibility aliases, forwarding files, or deprecation machinery for paths and classes that have not
  shipped in a release.
- Changing a sniff convention, default violation, configurable property, dependency, or consumer adoption policy.
- Publishing version 1.2, committing, pushing, or opening a pull request.

## Parent

PRD-00007 — Reusable Fight Coding Standard.

## Discovery Evidence

PHP_CodeSniffer derives a custom sniff identifier's first segment from the PHP namespace segment immediately
before `Sniffs`; the ruleset's `name` and `namespace` attributes cannot override that calculation. The approved
flat `Fight\Common\Standards\Phpcs\Sniffs` namespace therefore establishes `Phpcs.*` identifiers before the
standard's first release. No released consumer contract requires an alias or deprecation path.

## Outcome

Flattened the reusable standard to `src/Standards/Phpcs/ruleset.xml` and
`Fight\Common\Standards\Phpcs\Sniffs` without a compatibility shim for the unreleased paths or classes. The
ruleset remains named `FightCommon`; its ten custom sniffs now expose the approved `Phpcs.*` identifiers while
retaining their diagnostic codes, properties, built-in rules, and behavior. Root configuration, public guidance,
PHPStan path-scoped allowances, focused tests, and dist-like consumer fixtures all use the new layout.

## Verification

- The focused Standards surface passes 30 tests and 170 assertions, including installed-consumer loading,
  individual sniff selection, property overrides, fixes, and identifier enumeration.
- Rector, PHPStan, PHPCS, and both Deptrac architecture checks pass with zero violations, uncovered dependencies,
  skipped violations, or unassigned tokens.
- The complete disposable MySQL/PostgreSQL PHPUnit lifecycle passes 3,071 tests without skips.
- Clover is exact at 9,033/9,033 statements and 1,862/1,862 methods.
- Planning validation and `git diff --check` pass.
