---
id: T-00096
prd: PRD-00021
title: Complete Connect Systems Component Guidance
status: ready-for-agent
blocked_by: T-00094
---

# Complete Connect Systems Component Guidance

## Outcome

Give adopters accurate, directly discoverable integration guidance for every remaining Connect Systems
capability, applying the Mail-proven article contract to HTTP, authentication, cache, files, file transfer,
templating, routing, SMS, and sockets.

## Scope

- In scope: remaining Connect Systems guides, atlas metadata, portable contracts, optional packages, shipped
  adapters, supported framework composition, consequential behavior, failures, operations, relationships, and
  next steps.
- Out of scope: redoing Mail, promising unavailable native adapters, inventing format parity, changing runtime
  behavior, or duplicating complete framework guides.

## Acceptance Criteria

- [ ] Every Connect Systems atlas link reaches a canonical guide and Mail remains directly visible in the group.
- [ ] Each remaining guide accurately states purpose, layer ownership, required and optional dependencies,
      shortest portable usage, shipped adapters, supported composition, relevant failures, and next steps.
- [ ] HTTP and authentication guidance preserves protocol, signing, security, and error boundaries without
      presenting unsafe shorthand as production guidance.
- [ ] Files, file transfer, cache, templating, routing, SMS, and sockets distinguish portable ports from concrete
      providers and document meaningful operational behavior.
- [ ] Framework variants and configuration tabs appear only when supported by the actual integration.
- [ ] Public symbols, package requirements, links, examples, configuration syntax, and behavior claims match the
      source and selected compatibility guidance.
- [ ] Search, navigation, atlas links, anchors, copy controls, code scrolling, warnings, and both themes work for
      the completed group.
- [ ] Representative executable journeys prove high-risk copied behavior without adding an exhaustive provider
      matrix to routine documentation checks.

## Verification

- Build the generated group and validate atlas, navigation, search, links, anchors, symbols, and dependencies.
- Execute a small representative set of HTTP/security, storage/transfer, and outbound integration journeys and
  syntax-check copied configuration where useful.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Pending T-00094.
