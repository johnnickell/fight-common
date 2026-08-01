---
id: T-00018
prd: PRD-00007
title: Package the reusable FightCommon coding standard
status: ready-for-agent
blocked_by:
---

# Package the Reusable FightCommon Coding Standard

## Acceptance

- One `FightCommon` ruleset reproduces the Omphalos production PHP conventions without selecting consumer paths.
- The Omphalos custom sniffs and supporting helpers are ported into an optional Fight Common adapter surface with focused fixtures and complete coverage.
- PHP_CodeSniffer and Slevomat are development dependencies used to verify the package and Composer suggestions explain consumer opt-in requirements.
- A consumer can reference the installed standard and then declare its own files, exclusions, and explicit rule overrides.
- Fight Common's existing source scan remains green before the staged migration tickets begin.
