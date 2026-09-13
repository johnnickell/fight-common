---
id: T-00069
prd: PRD-00014
title: Deliver Shared PSR Interoperability and Portable Container Composition
status: done
blocked_by: T-00049,T-00051
---

# Deliver Shared PSR Interoperability and Portable Container Composition

## What to Build

Deliver one provider-neutral composition usable by Slim and standalone applications: PSR-15 JSON/JSend error
middleware, a PSR-17 JSend response factory, canonical PSR-6 and PSR-16 cache adapters, a PSR-18 client view over
Fight's configured synchronous HTTP transport, explicit Fight-container capability registrars, and a small Slim
named-route URL generator. Consumers select bounded capabilities and inject either Fight or PSR contracts without
framework-branded copies of shared behavior.

## Acceptance Criteria

- [x] PSR-15 JSON and JSend error middleware converts the neutral JSend envelope into standards-based request
      failure behavior without assuming a framework lifecycle.
- [x] The PSR-17 response factory preserves controller-selected status and headers, exact JSend JSON, and native
      PSR-7 response semantics without re-encoding the envelope.
- [x] Canonical PSR-6 and PSR-16 cache adapters satisfy their complete observable contracts, including misses,
      values, expiry, deletion, clearing, invalid keys, and failure translation.
- [x] The PSR-18 client implements `sendRequest()` by composing Fight's configured synchronous `send()` behavior;
      Fight's original client retains `sendAsync()` unchanged.
- [x] PSR-18 conformance returns 4xx and 5xx responses normally and translates request and network failures into
      the standard's required exception interfaces.
- [x] One configured transport can be resolved behind Fight's HTTP-client interface and as a decorating PSR-18
      client without claiming that arbitrary synchronous PSR-18 clients satisfy Fight's larger contract.
- [x] Fight-container registrars activate one bounded capability at a time from explicit service, handler,
      subscriber, filter, helper, and collaborator maps and use the container's existing factory callbacks.
- [x] Registrar conformance proves public aliases and lifecycle while unrelated capabilities, implicit scanning,
      and unselected optional packages remain absent.
- [x] Slim's URL generator translates named routes, parameters, and absolute or relative output through the native
      route collector without copying shared HTTP, container, or provider adapters.
- [x] A booted Fight-container/Slim composition resolves representative PSR, messaging, routing, and collaborator
      services using only explicitly selected capability registration.
- [x] Package metadata and documentation make every optional PSR implementation discoverable without adding a
      redundant wrapper where direct interface wiring is sufficient.

## Verification

Full submit gate, `./bin/planning-check`, shared PSR-15/17/6/16/18 conformance, Fight-container registration
tests, Slim route integration, optional-package absence probes, and an installed-package standalone journey.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Sources

WF-023 through WF-025 and ADR 0024.
