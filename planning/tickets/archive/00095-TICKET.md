---
id: T-00095
prd: PRD-00021
title: Complete Domain and Application Component Guidance
status: done
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

- [x] Every Model the Domain and Coordinate Application Behavior atlas link reaches a canonical task-oriented
      guide with accurate ownership and optional dependency metadata.
- [x] Each guide covers its purpose, shortest useful portable example, supported adapters or composition where
      applicable, consequential behavior, failures or operations where relevant, relationships, and next steps.
- [x] Messaging explains commands, queries, events, messages, handlers, filters, buses, synchronous and supported
      asynchronous paths without collapsing Domain, Application, and Adapter responsibilities.
- [x] Repositories and event sourcing distinguish contracts, adapters, durability, concurrency, publication, and
      operational boundaries accurately.
- [x] Examples and configuration use current public symbols and package requirements and avoid deprecated paths
      except where compatibility is the explicit subject.
- [x] Search, navigation, atlas routes, article anchors, diagrams, copy controls, code scrolling, and both themes
      work across the completed group.
- [x] Representative executable journeys and pragmatic syntax checks prove the highest-value copied behavior
      without attempting to execute every prose fragment.

## Verification

- Build the complete group with the strict MkDocs renderer and inspect atlas, navigation, search, links, anchors,
  public symbols, and dependency claims directly in the rendered site.
- Check copied examples against current source and existing production-code tests. Do not add documentation,
  generated-file, fixture, or tooling tests.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`; PHPUnit remains limited to
  production code.

## Completion Notes

Completed all ten Model the Domain and Coordinate Application Behavior guides, including Utilities, against
Mail's verified Atlas article contract. Each canonical route now leads with accurate ownership, dependencies, a
portable adoption path, consequential behavior, relationships, local contents, and next steps while retaining
useful detailed reference material and important anchors.

Messaging now separates Domain envelopes, Application ports, synchronous adapters, and the supported Symfony,
Laravel, and CodeIgniter asynchronous paths. Repository and event-sourcing guidance distinguishes contracts,
durability, concurrency, checkpoints, publication, retries, and operator-owned policy. Serialization, validation,
and dependency-injection guidance uses current public symbols and states its real trust and composition boundaries.

Verification passed the strict documentation validator, the existing Quick Start and Event Sourcing executable
journeys (18 tests, 197 assertions), desktop and compact browser review in light and dark themes, search navigation,
anchor navigation, responsive diagrams, copy controls, code scrolling, contrast-safe syntax highlighting, and
consistent first-line alignment. The alignment audit covered all 30 generated pages and 472 rendered code blocks.
`./bin/planning-check`, `git diff --check`, and the canonical persistent `./bin/build` completed successfully. The
validator retained the known upstream Material for MkDocs 2.0 compatibility warning.
