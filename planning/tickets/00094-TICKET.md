---
id: T-00094
prd: PRD-00021
title: Establish the Component Guide Contract Through Mail
status: ready-for-agent
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

- [ ] The guide states the Mail problem and its Domain, Application, and Adapter ownership before configuration.
- [ ] Required and optional dependencies are accurate and the shortest framework-neutral example uses public
      contracts and produces an observable result.
- [ ] Available adapters and supported framework composition are explicit without redefining Mail semantics.
- [ ] Alternate formats appear only where genuinely equivalent, with labels, filenames, keyboard operation,
      semantic tab state, and per-format copy controls.
- [ ] The kiln warning callout explains real recipient-override replacement behavior through icon, label, and
      prose rather than color alone.
- [ ] Failure modes, operational concerns, related components, and next steps give the reader a complete adoption
      path without duplicating source reference material.
- [ ] Mail is linked directly from Connect Systems, navigation, search, and relevant related guides.
- [ ] Examples, public symbols, packages, behavior claims, configuration syntax, links, and anchors are current.

## Verification

- Execute the representative Mail journey with deterministic test transport and prove recipient-override
  behavior at the public seam.
- Syntax-check representative supported configuration formats and exercise pointer and keyboard tab/copy use.
- Run strict documentation and artifact checks plus `./bin/planning-check`, `git diff --check`, and `./bin/build`.

## Completion Notes

Pending T-00090.
