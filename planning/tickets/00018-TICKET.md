---
id: T-00018
prd: PRD-00007
title: Package the reusable FightCommon coding standard
status: done
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

- [x] One `FightCommon` ruleset reproduces the Omphalos production PHP conventions without selecting consumer
  paths.
- [x] PHPCS tooling lives in the orthogonal Standards layer and depends only on PHP and the development-tool
  contracts it extends.
- [x] The Omphalos custom sniffs and supporting helpers are ported into the optional Standards surface with
  focused fixtures and complete coverage.
- [x] Parity fixtures prove the initial port against Omphalos behavior; after acceptance, Fight Common is the
  canonical implementation and the Omphalos copy is explicitly temporary.
- [x] PHP_CodeSniffer and Slevomat are development dependencies used to verify the package and Composer
  suggestions explain consumer opt-in requirements.
- [x] Fight Common remains a normal library package and does not require a Composer installer plugin for PHPCS
  discovery.
- [x] Consumer documentation provides a copy-ready PHPCS ruleset that loads the installed `FightCommon`
  standard, declares consumer-owned scan paths and exclusions, includes or excludes individual sniffs, and
  demonstrates explicit configurable-sniff overrides.
- [x] Omphalos adoption is handed off to separately planned work in its repository and does not block Fight
  Common 1.2 acceptance.
- [x] The ruleset, sniff identifiers, and configurable properties have stable public names with compatibility
  coverage that supports ADR 0004's release policy.
- [x] Fight Common's existing source scan remains green before the staged migration tickets begin.

## Outcome

Fight Common now owns the optional, path-free `FightCommon` PHP_CodeSniffer standard, including the accepted
Omphalos built-in rules, ten public custom sniffs, one supporting helper, stable diagnostics and configurable
properties, explicit dist-like consumer discovery, and copy-ready integration documentation. Omphalos parity
was proven across mechanical, member-layout, and documentation fixtures; its local copy is temporary pending
separately planned consumer adoption.

## Verification

- Rector, PHPStan, PHPCS, strict Composer validation, and planning validation pass.
- Full database-backed PHPUnit passes with 2,987 tests and 5,206 assertions.
- Clover reports exact coverage: 8,692/8,692 statements and 1,835/1,835 methods.
- Independent Standards and Spec reviews report no blocking findings.

## Parent

PRD-00007 — Reusable Fight Coding Standard.
