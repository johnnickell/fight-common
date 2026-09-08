---
id: T-00092
prd: PRD-00021
title: Deliver the Framework-Neutral Quick Start
status: done
blocked_by: T-00090
---

# Deliver the Framework-Neutral Quick Start

## Outcome

Give a PHP developer one short, working introduction to Fight Common that demonstrates portable composition
before branching into component-specific and supported framework guidance.

“Short” describes the concise primary guided path. Its collapsed, self-contained executable reference may be
substantial when completeness requires it; that reference supports the path without making the guide's narrative
or next-step decision tree longer.

## Scope

- In scope: prerequisites, installation, one realistic framework-neutral journey, architecture context, expected
  result, supported next paths, executable representative PHP, and current public-symbol verification.
- Out of scope: installing every optional adapter, beginning with Symfony, duplicating component articles, or
  changing the PHP API to simplify the example.

## Acceptance Criteria

- [x] The journey assumes PHP, Composer, and basic dependency-injection knowledge but no prior Fight experience.
- [x] The guide begins with the shortest valid installation and introduces only dependencies needed by its
      working path.
- [x] One coherent portable capability is configured, executed, and explained through public Fight Common
      contracts without requiring a framework.
- [x] The result is observable and the example explains relevant Domain, Application, and Adapter ownership.
- [x] The guide branches clearly into Architecture, Components, and supported framework composition only after
      the portable path works.
- [x] Every referenced public symbol, package requirement, command, link, and expected result matches the source.
- [x] Search, navigation, anchors, copy controls, code scrolling, and both themes preserve the journey in the
      generated site.

## Verification

- Execute the representative PHP journey against the installed package and validate copied configuration where
  applicable.
- Run strict documentation and link checks, `./bin/planning-check`, `git diff --check`, and `./bin/build`.

## Completion Notes

The executable fixture, framework-neutral guide, public-compatibility contract, and generated-artifact
structure, symbol, anchor, search, and next-path contracts are verified. Brave qualification covered mobile,
tablet, desktop, and wide layouts in both themes, including navigation, search, anchors, copy feedback, the
expandable example, and code overflow. The canonical Screen build completed with exit `0`, 4,145 tests, 27,673
assertions, and exact 18,579/18,579 statement coverage.
