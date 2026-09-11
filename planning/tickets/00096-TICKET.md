---
id: T-00096
prd: PRD-00021
title: Complete Connect Systems Component Guidance
status: done
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

- [x] Every Connect Systems atlas link reaches a canonical guide and Mail remains directly visible in the group.
- [x] Each remaining guide accurately states purpose, layer ownership, required and optional dependencies,
      shortest portable usage, shipped adapters, supported composition, relevant failures, and next steps.
- [x] HTTP and authentication guidance preserves protocol, signing, security, and error boundaries without
      presenting unsafe shorthand as production guidance.
- [x] Files, file transfer, cache, templating, routing, SMS, and sockets distinguish portable ports from concrete
      providers and document meaningful operational behavior.
- [x] Framework variants and configuration tabs appear only when supported by the actual integration.
- [x] Public symbols, package requirements, links, examples, configuration syntax, and behavior claims match the
      source and selected compatibility guidance.
- [x] Search, navigation, atlas links, anchors, copy controls, code scrolling, warnings, and both themes work for
      the completed group.
- [x] Representative executable journeys prove high-risk copied behavior without adding an exhaustive provider
      matrix to routine documentation checks.

## Verification

- Build the group with the strict MkDocs renderer and inspect atlas, navigation, search, links, anchors, symbols,
  and dependencies directly in the rendered site.
- Check copied examples against current source and existing production-code tests, and syntax-check configuration
  with its owning tool where useful. Do not add documentation, generated-file, fixture, or tooling tests.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`; PHPUnit remains limited to
  production code.

## Completion Notes

Completed all nine remaining Connect Systems guides against Mail's verified Atlas article contract. HTTP Client
and Auth now preserve PSR, signing, replay, credential, and validation boundaries; Cache, Files, and File Transfer
separate storage and transfer ports from providers; and Templating, Routing, SMS, and Sockets document their real
composition, failure, privacy, delivery, and operator-owned behavior.

All ten Connect Systems routes, including unchanged Mail, remain directly discoverable. Public symbols,
dependencies, anchors, examples, adapters, and framework claims were checked against source, Composer metadata,
and existing production tests. PHP examples render with token-level syntax highlighting, and the shared shell now
restores the homepage mobile drawer plus compact content and footer padding.

The strict documentation validator passed after every slice. Existing HTTP/Auth, Cache/Files/File Transfer,
Templating/Routing, and SMS/Sockets tests passed with 614 tests and 1,772 assertions in total. Desktop and compact
review covered light and dark themes, search, rail and drawer navigation, local contents, warnings, copy controls,
anchors, and horizontal code scrolling; John accepted the final visual result. `./bin/planning-check`,
`git diff --check`, and the canonical persistent `./bin/build` completed successfully with 3,641 tests, 20,637
assertions, and exact statement coverage of 10,089/10,089.
