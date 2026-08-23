---
id: T-00072
prd: PRD-00015
title: Deliver Yii Adapters Providers and Proven Fallbacks
status: ready-for-agent
blocked_by: T-00059,T-00069
---

# Deliver Yii Adapters, Providers, and Proven Fallbacks

## What to Build

Deliver Yii's stable native transaction and routing adapters, capability-scoped configuration/providers, and
conformance prototypes for Yii Mail, View, and Filesystem. Wire Yii's accepted PSR and provider facilities for
the remaining capabilities, while making stable queue support explicitly unavailable until its upstream release
gate can be proven.

## Acceptance Criteria

- [ ] Yii DB UnitOfWork preserves callback results, commit, rollback and original failure propagation, closed
      lifecycle, and explicit nested-transaction rejection through the shared transaction suite.
- [ ] Yii URL generation preserves named routes, parameters, query values, absolute or relative output, and
      failure behavior through the shared routing suite.
- [ ] Capability configuration groups and providers register only explicitly selected public services, aliases,
      collaborators, and lifecycle behavior.
- [ ] A real Yii container boots each selected capability group while unrelated integrations and optional
      packages remain absent.
- [ ] Yii Mail, View, and Filesystem prototypes run the complete Fight mail, templating, and filesystem suites;
      each passing prototype ships, while each failure records the exact gap and proves its Symfony Mailer,
      Twig/provider, or Symfony Filesystem fallback.
- [ ] Cache and logging use Yii's accepted standard interfaces directly, and outbound HTTP uses the shared PSR
      lane and selected provider without redundant Fight-branded wrappers.
- [ ] Flysystem, Symfony Process, Twilio, Mercure, and shared health, audit, and metrics adapters remain reusable
      provider compositions where Yii adds no distinct complete contract.
- [ ] Stable Yii Queue support is reported as unavailable rather than skipped, passed, or silently omitted.
- [ ] Experimental queue wiring may reuse the neutral handlers but cannot satisfy the `1.2` support claim or
      become a stable public adapter without stable core and production-broker packages plus serialization,
      acknowledgement, retry, failure, signal, and long-running-state evidence.
- [ ] No framework guard, identity, session, or principal is hidden behind Fight's boolean authenticator.

## Verification

Full submit gate, `./bin/planning-check`, shared transaction/routing/mail/template/filesystem conformance, booted
Yii provider tests, accepted PSR/provider wiring tests, optional-package absence probes, and explicit queue-gate
evidence.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.

## Decision Sources

WF-021, WF-024, and ADR 0024.
