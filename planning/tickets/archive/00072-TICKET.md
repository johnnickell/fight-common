---
id: T-00072
prd: PRD-00015
title: Deliver Yii Adapters Providers and Proven Fallbacks
status: done
blocked_by: T-00059,T-00069
---

# Deliver Yii Adapters, Providers, and Proven Fallbacks

## What to Build

Deliver Yii's stable native transaction and routing adapters, capability-scoped configuration/providers, and
conformance prototypes for Yii Mail, View, and Filesystem. Wire Yii's accepted PSR and provider facilities for
the remaining capabilities, while making stable queue support explicitly unavailable until its upstream release
gate can be proven.

## Acceptance Criteria

- [x] Yii DB UnitOfWork preserves callback results, commit, rollback and original failure propagation, closed
      lifecycle, and explicit nested-transaction rejection through the shared transaction suite.
- [x] Yii URL generation preserves named routes, parameters, query values, absolute or relative output, and
      failure behavior through the shared routing suite.
- [x] Capability configuration groups and providers register only explicitly selected public services, aliases,
      collaborators, and lifecycle behavior.
- [x] A real Yii container boots each selected capability group while unrelated integrations and optional
      packages remain absent.
- [x] Yii Mail, View, and Filesystem prototypes run the complete Fight mail, templating, and filesystem suites;
      each passing prototype ships, while each failure records the exact gap and proves its Symfony Mailer,
      Twig/provider, or Symfony Filesystem fallback.
- [x] Cache and logging use Yii's accepted standard interfaces directly, and outbound HTTP uses the shared PSR
      lane and selected provider without redundant Fight-branded wrappers.
- [x] Flysystem, Symfony Process, Twilio, Mercure, and shared health, audit, and metrics adapters remain reusable
      provider compositions where Yii adds no distinct complete contract.
- [x] Stable Yii Queue support is reported as unavailable rather than skipped, passed, or silently omitted.
- [x] Experimental queue wiring may reuse the neutral handlers but cannot satisfy the `1.2` support claim or
      become a stable public adapter without stable core and production-broker packages plus serialization,
      acknowledgement, retry, failure, signal, and long-running-state evidence.
- [x] No framework guard, identity, session, or principal is hidden behind Fight's boolean authenticator.

## Verification

Full submit gate, `./bin/planning-check`, shared transaction/routing/mail/template/filesystem conformance, booted
Yii provider tests, accepted PSR/provider wiring tests, optional-package absence probes, and explicit queue-gate
evidence.

## Implementation Evidence

- Published native Yii DB transactional UnitOfWork, URL generation, and View templating adapters, with real Yii
  SQLite, FastRoute, View, Config, and strict DI-container behavior exercised through shared conformance suites.
- Independently selectable persistence, routing, HTTP, synchronous messaging, mail, view, and filesystem groups
  activate only their bounded contracts. Cache and logging use PSR interfaces directly; HTTP reuses the shared
  PSR-18 view; no aggregate all-capabilities provider exists.
- The native Yii Mail prototype failed independent-part charset and exact valid-CID behavior, so the proven Symfony
  Mailer fallback remains selected. The native Yii Files prototype passed only recursive directory creation in the
  22-case suite, so the canonical Symfony Filesystem fallback remains selected. Yii View passed all shared cases
  and ships natively.
- Stable Yii Queue is explicitly unavailable: no stable adapter, asynchronous binding, production requirement, or
  suggestion was published; neutral synchronous handlers remain reusable without making a queue support claim.
- The canonical `./bin/build` passed 3,849 tests with 14,237 assertions and exact 17,662/17,662 statement coverage.
  Fresh independent Standards and Spec review reported zero findings and all ten acceptance criteria passed.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-021, WF-024, and ADR 0024.
