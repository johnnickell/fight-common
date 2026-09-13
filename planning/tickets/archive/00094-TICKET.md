---
id: T-00094
prd: PRD-00021
title: Establish the Component Guide Contract Through Mail
status: done
blocked_by: T-00090
---

# Establish the Component Guide Contract Through Mail

## Outcome

Make Mail the approachable production example of the component-guide contract, showing readers what the
component solves, where it belongs, how to install and use it portably, how supported adapters are wired, which
behaviors matter, and where to go next.

## Scope

- In scope: Mail purpose, ownership and dependencies, shortest portable journey, supported adapters and framework
  composition, genuine equivalent configuration tabs, consequential recipient-override behavior, failures,
  operations, related components, next steps, and generated-site discovery.
- Out of scope: inventing configuration parity, changing Mail runtime behavior, rewriting unrelated component
  pages, or requiring every guide to have identical length and sections.

## Acceptance Criteria

- [x] The guide states the Mail problem and its Domain, Application, and Adapter ownership before configuration.
- [x] Required and optional dependencies are accurate and the shortest framework-neutral example uses public
      contracts and produces an observable result.
- [x] Available adapters and supported framework composition are explicit without redefining Mail semantics.
- [x] Alternate formats appear only where genuinely equivalent, with labels, filenames, keyboard operation,
      semantic tab state, and per-format copy controls.
- [x] The kiln warning callout explains real recipient-override replacement behavior through icon, label, and
      prose rather than color alone.
- [x] Failure modes, operational concerns, related components, and next steps give the reader a complete adoption
      path without duplicating source reference material.
- [x] Mail is linked directly from Connect Systems, navigation, search, and relevant related guides.
- [x] Examples, public symbols, packages, behavior claims, configuration syntax, links, and anchors are current.

## Verification

- Confirm the representative Mail example and recipient-override claim against the existing public production-code
  tests; this documentation ticket adds no documentation, generated-file, fixture, or tooling tests.
- Build with the strict MkDocs renderer, syntax-check representative supported configuration directly, and
  exercise pointer and keyboard tab/copy use in the rendered site.
- Run `./bin/planning-check`, `git diff --check`, and `./bin/build`; PHPUnit remains limited to production code.

## Completion Notes

Completed the portable Mail adoption journey, accurate Symfony and Laravel delivery guidance, framework fallbacks,
safe-operation boundaries, reciprocal discovery, and behavioral recipient-replacement proof without changing the
runtime API. The copied portable example and representative YAML, XML, and PHP configuration passed direct checks;
focused Mail tests passed with 79 tests and 262 assertions; rendered browser review covered the guide, configuration
tabs, standard square copy controls, navigation, search, anchors, themes, and responsive layouts. The canonical
background `./bin/build` passed with 3,641 tests, 20,638 assertions, and exact 10,089/10,089 statement coverage.
