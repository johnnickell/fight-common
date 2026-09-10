---
id: T-00093
prd: PRD-00021
title: Explain Hexagonal Architecture and CQRS Visually
status: done
blocked_by: T-00090
---

# Explain Hexagonal Architecture and CQRS Visually

## Outcome

Give architects and adopters a prominent, diagram-led explanation of how Fight Common supports Hexagonal
Architecture and CQRS, so component examples can rely on shared understanding of inward dependencies, ports,
adapters, messages, and ownership.

## Scope

- In scope: conceptual learning path, labeled dependency and message-flow diagrams, layer responsibilities,
  framework boundaries, repository enforcement context, Fight Common examples, related component routes, and
  accessible responsive rendering.
- Out of scope: turning Architecture into a quick-start subsection, requiring it before component discovery,
  generic architecture theory unrelated to Fight Common, or changing production dependencies.

## Acceptance Criteria

- [x] The guide explains Adapter to Application to Domain dependency direction, ports and adapters, protected
      Domain logic, Application coordination, and framework ownership in Fight Common terms.
- [x] Commands, queries, events, handlers, filters, buses, and dispatchers are related to their responsibilities
      and layers through a clear CQRS flow.
- [x] Diagrams communicate through labels, position, and edge styles before color and remain understandable in
      light, dark, monochrome, narrow, and zoomed contexts.
- [x] Real component examples connect the concepts to relevant guides without turning the page into an API list.
- [x] Repository Deptrac enforcement is explained as evidence of the model, not as the whole architecture story.
- [x] Architecture remains a first-class homepage and navigation route separate from the component atlas.
- [x] Search, anchors, local contents, next steps, and code or diagram scrolling work in the generated site.

## Verification

- Human-review every diagram and explanation against current dependency rules and public contracts.
- Inspect the guide at narrow and wide sizes, 200% zoom, and both themes; verify non-color meaning.
- Build the site with the strict MkDocs renderer and inspect its diagnostics and rendered links directly. Do not
  add tests for documentation content, generated HTML, CSS, anchors, search entries, or documentation tooling.
- Run `./bin/planning-check`, `git diff --check`, and `./bin/build`; PHPUnit remains limited to production code.

## Completion Notes

Delivered the approved diagram-led Architecture guide with an inward dependency model, ports-and-adapters
ownership, framework boundaries, real Mail and repository examples, and distinct command, query, and event flows
through the current public messaging contracts. The CQRS presentation keeps dispatch, query return, and event
fan-out distinguishable through labels and edge styles, reflows without page-level or internal overflow, and
retains useful anchors, search, local contents, and next-step routes.

The shared guide shell now gives both Architecture and Quick Start roomier navigation rails. Material's native
code-copy control sizing is restored across the site, and its feedback dialog has readable light- and dark-theme
contrast. Direct browser inspection covered 375, 768, 1024, 1280, and 1440 pixel guide layouts, CQRS edge widths,
both themes, copy feedback, and browser diagnostics. John approved the local rendered page on 2026-09-10.
The canonical local `./bin/build` then completed with exit `0`, 3,641 tests, 20,620 assertions, and exact
10,089/10,089 statement coverage.
