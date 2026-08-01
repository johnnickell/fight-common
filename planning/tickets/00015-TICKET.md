---
id: T-00015
prd: PRD-00006
title: Add Symfony event-mapping autoconfiguration
status: ready-for-agent
blocked_by: T-00006
---

# Add Symfony Event-Mapping Autoconfiguration

## What to Build

Let Symfony consumers auto-discover bounded-context mapping providers and compose them into the configured Event Mapper while preserving the same portable registration and validation behavior used by manual construction.

## Blocked By

- T-00006 — Implement event mapping and upcasting.

## Acceptance

- [ ] Symfony integration auto-tags `EventMappingProvider` implementations and composes them into the configured Event Mapper during container compilation.
- [ ] Each provider contributes its durable namespace and typed mappings through the same portable core contract used by direct registration.
- [ ] Duplicate or invalid mappings fail container compilation through the Event Mapper's existing validation.
- [ ] Direct construction and manual registration remain fully supported without Symfony.
- [ ] Container behavior has complete coverage.
- [ ] This ticket remains a non-release-blocking 1.2 stretch goal.
