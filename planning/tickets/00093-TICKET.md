---
id: T-00093
prd: PRD-00021
title: Explain Hexagonal Architecture and CQRS Visually
status: ready-for-agent
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

- [ ] The guide explains Adapter to Application to Domain dependency direction, ports and adapters, protected
      Domain logic, Application coordination, and framework ownership in Fight Common terms.
- [ ] Commands, queries, events, handlers, filters, buses, and dispatchers are related to their responsibilities
      and layers through a clear CQRS flow.
- [ ] Diagrams communicate through labels, position, and edge styles before color and remain understandable in
      light, dark, monochrome, narrow, and zoomed contexts.
- [ ] Real component examples connect the concepts to relevant guides without turning the page into an API list.
- [ ] Repository Deptrac enforcement is explained as evidence of the model, not as the whole architecture story.
- [ ] Architecture remains a first-class homepage and navigation route separate from the component atlas.
- [ ] Search, anchors, local contents, next steps, and code or diagram scrolling work in the generated site.

## Verification

- Human-review every diagram and explanation against current dependency rules and public contracts.
- Inspect the guide at narrow and wide sizes, 200% zoom, and both themes; verify non-color meaning.
- Run strict documentation, link, and artifact checks plus `./bin/planning-check`, `git diff --check`, and
  `./bin/build`.

## Completion Notes

Pending T-00090.
