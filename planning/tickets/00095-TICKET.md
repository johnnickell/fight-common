---
id: T-00095
prd: PRD-00021
title: Complete Domain and Application Component Guidance
status: ready-for-agent
blocked_by: T-00094
---

# Complete Domain and Application Component Guidance

## Outcome

Make every Model the Domain and Coordinate Application Behavior capability directly discoverable and
trustworthy, using the proven guide contract while allowing each component to emphasize its actual adoption and
operational concerns. Use Messaging to prove that the structure survives a dense cross-layer subject.

## Scope

- In scope: values, collections, specifications, repositories, event sourcing, messaging, validation,
  serialization, dependency injection, their atlas metadata, current examples, relationships, and next steps.
- Out of scope: changing public contracts, forcing empty guide sections, framework-only composition unrelated to
  these capabilities, or generated API reference for every symbol.

## Acceptance Criteria

- [ ] Every Model the Domain and Coordinate Application Behavior atlas link reaches a canonical task-oriented
      guide with accurate ownership and optional dependency metadata.
- [ ] Each guide covers its purpose, shortest useful portable example, supported adapters or composition where
      applicable, consequential behavior, failures or operations where relevant, relationships, and next steps.
- [ ] Messaging explains commands, queries, events, messages, handlers, filters, buses, synchronous and supported
      asynchronous paths without collapsing Domain, Application, and Adapter responsibilities.
- [ ] Repositories and event sourcing distinguish contracts, adapters, durability, concurrency, publication, and
      operational boundaries accurately.
- [ ] Examples and configuration use current public symbols and package requirements and avoid deprecated paths
      except where compatibility is the explicit subject.
- [ ] Search, navigation, atlas routes, article anchors, diagrams, copy controls, code scrolling, and both themes
      work across the completed group.
- [ ] Representative executable journeys and pragmatic syntax checks prove the highest-value copied behavior
      without attempting to execute every prose fragment.

## Verification

- Build the complete generated group and validate atlas, navigation, search, links, anchors, public symbols, and
  dependency claims.
- Execute representative Domain, messaging, repository or event-sourcing, and validation journeys selected for
  copied-behavior risk.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Pending T-00094.
