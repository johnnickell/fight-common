---
id: T-00074
prd: PRD-00015
title: Deliver CodeIgniter Native Adapters and Prove Fallbacks
status: done
blocked_by: T-00049,T-00069,T-00073
---

# Deliver CodeIgniter Native Adapters and Prove Fallbacks

## What to Build

Complete CodeIgniter's remaining capability journey with native cache, JSend/error response, and URL-generation
adapters plus conformance prototypes for Mail, View, and Filesystem. Wire its native PSR-3 logger and the accepted
shared providers for outbound HTTP, storage, process, observability, SMS, and sockets without redundant wrappers.

## Acceptance Criteria

- [x] `CodeIgniterCache` satisfies Fight's complete cache contract through the current native `CacheInterface`,
      including misses, values, expiry, remember behavior, deletion, clearing, invalid input, and failures.
- [x] The separate official PSR cache bridge remains a valid documented wire composition without making the
      native adapter redundant or mandatory.
- [x] Native JSend/error response creation consumes the neutral envelope and preserves caller-selected status,
      headers, exact JSON, and encoding failures.
- [x] Native URL generation preserves named routes, parameters, query values, absolute or relative output, and
      failure behavior.
- [x] Mail, View, and Filesystem prototypes run the complete Fight conformance suites; a passing prototype ships,
      while a failure records the exact gap and proves Symfony Mailer, Twig/PHP, or Symfony Filesystem as the
      selected fallback.
- [x] CodeIgniter's native PSR-3 logger is wired directly without a Fight-branded logger wrapper.
- [x] Guzzle/PSR-18, Flysystem, Symfony Process, Twilio, Mercure, and shared health, audit, and metrics adapters
      remain provider compositions where CodeIgniter exposes no distinct complete Fight contract.
- [x] Capability service delegates register each shipped adapter or proven fallback independently without
      activating unrelated optional packages.
- [x] Application-owned templates, mail content, routes, credentials, service overrides, and operations policy
      remain downstream.
- [x] Every ship or fallback claim links to shared conformance evidence and one booted CodeIgniter composition.

## Verification

Full submit gate, `./bin/planning-check`, shared cache/HTTP/routing/mail/template/filesystem conformance, native
prototype and fallback evidence, booted capability-delegate tests, and optional-package absence probes.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-022, WF-024, and ADR 0024.
