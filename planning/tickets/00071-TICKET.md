---
id: T-00071
prd: PRD-00015
title: Deliver Laravel Native Adapters and Prove Fallbacks
status: done
blocked_by: T-00049,T-00060,T-00069,T-00070
---

# Deliver Laravel Native Adapters and Prove Fallbacks

## What to Build

Complete Laravel's remaining capability journey with native password hashing and validation, cache, JSend/error
responses, URL generation, Blade templating, mail, and broadcasting adapters plus conformance prototypes for
native storage, filesystem, outbound HTTP, process, and Pulse metrics. Each prototype either becomes a complete
adapter or records its exact gap and proves the accepted shared fallback.

## Acceptance Criteria

- [x] Password hashing and validation preserve Fight's hashing, verification, rehash, invalid-input, and failure
      behavior through Laravel's native service.
- [x] The Laravel cache adapter satisfies the complete Fight cache conformance suite, including native expiry,
      misses, remember behavior, deletion, clearing, and failures.
- [x] Native JSend/error response creation consumes the neutral envelope and preserves caller-selected status,
      headers, exact JSON, and encoding failures.
- [x] Native URL generation preserves named routes, parameters, query values, absolute or relative output, and
      failure behavior.
- [x] Blade, mail, and private broadcasting adapters satisfy the complete templating, mail, and private-publication
      contracts without owning application templates, mail content, authorization, or channel policy.
- [x] Laravel's PSR-3 logger is wired directly without a Fight-branded wrapper or a separately imposed logging
      package.
- [x] Native FileStorage, Filesystem, HTTP client, Process, and Pulse metrics prototypes run the same shared
      behavioral suites as shipped adapters.
- [x] A passing prototype publishes the native adapter; a failing prototype records the exact missing operation
      or value and proves Flysystem, Symfony Filesystem, Guzzle/PSR-18, Symfony Process, or shared metrics as the
      selected fallback.
- [x] Capability-scoped Laravel providers register each shipped adapter or tested fallback independently and do
      not activate unrelated packages.
- [x] Application-owned templates, messages, routes, credentials, broadcasting authorization, and operations
      configuration remain downstream.

## Verification

Full submit gate, `./bin/planning-check`, shared security/cache/HTTP/routing/templating/mail/publication
conformance, native prototype evidence, fallback evidence, booted capability-provider tests, and optional-package
absence probes.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-020, WF-024, and ADR 0024.
