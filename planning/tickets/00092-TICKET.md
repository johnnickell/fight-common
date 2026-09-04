---
id: T-00092
prd: PRD-00021
title: Deliver the Framework-Neutral Quick Start
status: ready-for-agent
blocked_by: T-00090
---

# Deliver the Framework-Neutral Quick Start

## Outcome

Give a PHP developer one short, working introduction to Fight Common that demonstrates portable composition
before branching into component-specific and supported framework guidance.

## Scope

- In scope: prerequisites, installation, one realistic framework-neutral journey, architecture context, expected
  result, supported next paths, executable representative PHP, and current public-symbol verification.
- Out of scope: installing every optional adapter, beginning with Symfony, duplicating component articles, or
  changing the PHP API to simplify the example.

## Acceptance Criteria

- [ ] The journey assumes PHP, Composer, and basic dependency-injection knowledge but no prior Fight experience.
- [ ] The guide begins with the shortest valid installation and introduces only dependencies needed by its
      working path.
- [ ] One coherent portable capability is configured, executed, and explained through public Fight Common
      contracts without requiring a framework.
- [ ] The result is observable and the example explains relevant Domain, Application, and Adapter ownership.
- [ ] The guide branches clearly into Architecture, Components, and supported framework composition only after
      the portable path works.
- [ ] Every referenced public symbol, package requirement, command, link, and expected result matches the source.
- [ ] Search, navigation, anchors, copy controls, code scrolling, and both themes preserve the journey in the
      generated site.

## Verification

- Execute the representative PHP journey against the installed package and validate copied configuration where
  applicable.
- Run strict documentation and link checks, `./bin/planning-check`, `git diff --check`, and `./bin/build`.

## Completion Notes

Pending T-00090.
