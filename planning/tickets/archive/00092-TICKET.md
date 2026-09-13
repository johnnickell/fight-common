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

- In scope: framework starter selection with an honest pre-1.0 release note, prerequisites, installation, one
  realistic framework-neutral journey, architecture context, expected result, supported next paths, executable
  representative PHP, and current public-symbol verification.
- Out of scope: installing every optional adapter, beginning with Symfony, duplicating component articles, or
  changing the PHP API to simplify the example.

## Acceptance Criteria

- [x] The journey assumes PHP, Composer, and basic dependency-injection knowledge but no prior Fight experience.
- [x] The guide begins with a framework picker and accurate starter-release status, then gives the shortest valid
      framework-neutral installation and introduces only dependencies needed by its working path.
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

The approved reimplementation opens with a five-starter framework picker and clearly states that the intended
`1.0.0` starter releases are still being prepared. The framework-neutral path then presents the supporting domain
type, command, event, command handler, follow-up command, event subscriber, fulfillment handler, composition, and
dispatch call as individually explained, syntax-highlighted, copyable examples. The event intentionally precedes
the handler that dispatches it; the reader-visible full test fixture was removed while the executable fixture
remains the single source behind every snippet.

Generated-artifact contracts verify the exact narrative order, nine named snippet surfaces, public symbols,
anchors, search, next paths, and the absence of the full fixture from reader-visible output. Browser verification
confirmed 11 rendered code blocks with 11 copy controls, the framework picker, event-before-handler order, search,
navigation, both themes, and responsive code overflow. Human review accepted the rendered guide. The canonical
local `./bin/build` completed with exit `0`, 4,145 tests, 27,730 assertions, and exact 18,579/18,579 statement
coverage.
