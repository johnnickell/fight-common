---
id: T-00045
prd: PRD-00007
title: Repair mechanical PHPCBF fixer output
status: ready-for-agent
blocked_by: T-00018
---

# Repair Mechanical PHPCBF Fixer Output

## What to Build

Make the strict-types and blank-line-before-return fixers emit canonical, idempotent PHP formatting in the
production contexts exposed by T-00019. Preserve the existing convention, public sniff identifiers, and
diagnostic codes while adding focused regressions before changing either fixer.

## Blocked By

T-00018 — Package the Reusable FightCommon Coding Standard.

## Acceptance Criteria

- [ ] A focused regression proves `FightCommon.Files.RequireStrictTypes` inserts `declare(strict_types=1);`
  with exactly the canonical surrounding blank lines when a production file begins with an open tag followed
  by namespace content.
- [ ] A focused regression proves `FightCommon.Formatting.RequireBlankLineBeforeReturn` adds exactly one blank
  line without displacing the return statement from its existing indentation.
- [ ] Applying PHPCBF a second time to each regression fixture makes no further change.
- [ ] The fixes preserve the existing rule behavior, public sniff identifiers, and diagnostic codes and do not
  introduce a new default violation for previously compliant source.
- [ ] Mechanical convention, custom-sniff edge, consumer-contract, and compatibility coverage remain green.
- [ ] The complete submit gate remains green with exact complete coverage.

## Out of Scope

- Changing the strict-types or return-spacing convention.
- Adding a new sniff, diagnostic, configuration property, or default violation.
- Reworking unrelated PHPCBF fixers or formatting rules.
- Repeating or closing the T-00019 source migration.

## Parent

PRD-00007 — Reusable Fight Coding Standard.

## Discovery Evidence

During T-00019, the strict-types fixer emitted excess vertical whitespace around the inserted declaration,
and the blank-line-before-return fixer moved two indented return statements to column one. The migration was
normalized manually and kept in scope; this ticket owns regression-first correction of the reusable fixers.
The reusable sniffs already exist through T-00018, so the regression repair does not depend on landing the
source migration that exposed it.
