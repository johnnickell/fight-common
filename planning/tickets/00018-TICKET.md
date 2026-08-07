---
id: T-00018
prd: PRD-00007
title: Package the reusable FightCommon coding standard
status: ready-for-agent
blocked_by:
---

# Package the Reusable FightCommon Coding Standard

## What to Build

Publish the optional `FightCommon` PHP_CodeSniffer standard as a complete consumer-facing capability. A
consumer can explicitly load it, choose repository-owned scan scope, include or exclude individual sniffs,
and override documented properties while Fight Common proves parity with the accepted Omphalos behavior.

## Blocked By

None — can start immediately.

## Acceptance Criteria

- [ ] One `FightCommon` ruleset reproduces the Omphalos production PHP conventions without selecting consumer
  paths.
- [ ] PHPCS tooling lives in the orthogonal Standards layer and depends only on PHP and the development-tool
  contracts it extends.
- [ ] The Omphalos custom sniffs and supporting helpers are ported into the optional Standards surface with
  focused fixtures and complete coverage.
- [ ] Parity fixtures prove the initial port against Omphalos behavior; after acceptance, Fight Common is the
  canonical implementation and the Omphalos copy is explicitly temporary.
- [ ] PHP_CodeSniffer and Slevomat are development dependencies used to verify the package and Composer
  suggestions explain consumer opt-in requirements.
- [ ] Fight Common remains a normal library package and does not require a Composer installer plugin for PHPCS
  discovery.
- [ ] Consumer documentation provides a copy-ready PHPCS ruleset that loads the installed `FightCommon`
  standard, declares consumer-owned scan paths and exclusions, includes or excludes individual sniffs, and
  demonstrates explicit configurable-sniff overrides.
- [ ] Omphalos adoption is handed off to separately planned work in its repository and does not block Fight
  Common 1.2 acceptance.
- [ ] The ruleset, sniff identifiers, and configurable properties have stable public names with compatibility
  coverage that supports ADR 0004's release policy.
- [ ] Fight Common's existing source scan remains green before the staged migration tickets begin.

## Parent

PRD-00007 — Reusable Fight Coding Standard.
