# Select the framework-adapter support matrix

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Research Laravel-native adapter seams](WF-020-laravel-native-adapter-seams.md), [Research Yii-native adapter seams](WF-021-yii-native-adapter-seams.md), [Research CodeIgniter-native adapter seams](WF-022-codeigniter-native-adapter-seams.md), [Research Symfony, Slim, and standalone adapter seams](WF-023-symfony-slim-and-standalone-adapter-seams.md), [Research PSR interoperability and adapter seams](WF-025-psr-interoperability-and-adapter-seams.md)

## Question

For every public Fight Common Domain and Application capability, which concrete adapters and
service-container integrations belong in Fight Common, which neutral adapters are reused unchanged, and
which configuration remains starter-owned?

## Must decide

- one explicit capability-by-framework matrix covering the full audited surface;
- one standards-first PSR matrix distinguishing direct interface use, lossless adapters, deliberate semantic
  mismatches, and standards that have no corresponding Fight capability;
- the minimum reusable adapter criterion and the line between adapter behavior and project configuration;
- async command and event delivery, neutral message handlers, serializers, transaction timing, retries,
  failure handling, and worker ownership;
- persistence namespace and UnitOfWork implementations, including native and Doctrine-backed frameworks;
- package suggestions, optional dependency isolation, supported-version ranges, and auto-discovery policy;
- Composer and documentation corrections required for each standards support claim, including the current
  PSR-6/PSR-20 cache-versus-clock misstatement;
- whether any capability needs an inward additive contract or a `2.0.0` change; and
- which existing implementation tickets remain valid, require rewriting, or should be replaced.

## Decisions landed during grilling

- Stable async adapters transport one complete Fight message envelope and delegate to neutral synchronous
  handlers. Delivery is at least once and uses post-commit submission where available; it does not claim an
  atomic outbox.
- Yii Queue remains starter-owned experimental composition until the core package and a production broker
  adapter publish compatible stable releases.
- Fight Common 1.2 adapts exact authentication/security seams only. The downstream Fight AccessControl project
  owns project-level authentication and principal adaptation; framework guards are not hidden behind the current
  boolean PSR-7 `Authenticator`.
- Framework service-container integrations are capability-scoped and explicitly selected; there is no provider
  that activates every optional adapter.
- Fight Common's own container receives capability-scoped registration classes using explicit service and
  handler maps. Project collaborators use the container's existing callback factories; registration does not
  add implicit scanning or another callback layer.
- Native framework adapters are attempted against current public APIs and ship only when the complete Fight
  contract passes. Official companion packages are selected case by case. A direct CodeIgniter cache adapter is
  an accepted prototype candidate even though the official PSR cache bridge remains a valid composition.
- Optional framework/provider packages remain Fight Common development dependencies and Composer suggestions;
  each starter requires only its selected runtime stack.
- The initial support rule requires library conformance evidence plus a booted installed-package starter journey.
  This evidence rule remains explicitly reviewable if implementation shows disproportionate cost.
- The accepted framework catalog in ADR 0024 selects shared PSR/provider adapters; Laravel messaging,
  transactions, security helpers, cache, HTTP responses, routing, Blade, mail, and broadcasting; Yii
  transactions, routing, and providers; CodeIgniter cache, transactions, Queue, HTTP responses, routing, and
  service delegates; the canonically namespaced existing Symfony integrations; and shared PSR/provider
  composition plus a routing adapter for Slim. The catalog also names the native adapters that begin as
  conformance prototypes instead of promised APIs.
- Fight Common publishes a PSR-18 client implementation that composes the existing Fight `HttpClient` and
  exposes its synchronous `send()` behavior to PSR-18 consumers. It does not adapt arbitrary sync-only PSR-18
  clients into Fight's async-capable interface.
- Container wiring may register one configured transport behind Fight `HttpClient` and a `Psr18Client` view of
  that transport behind PSR-18 `ClientInterface`, so consumers inject only the contract they need.
- Every accepted **ship** adapter is part of the 1.2 support claim. A failed native prototype blocks 1.2 only
  when no documented and tested fallback remains. Later additive support may ship in 1.3; stable Yii Queue
  support is a named candidate. Incompatible cleanup remains reserved for 2.0.
- The planning handoff updates PRD-00014 and PRD-00015 before ticket publication. Implementation is divided into
  shared, Symfony, two Laravel, one Yii, two CodeIgniter, and final package/documentation/certification vertical
  slices. T-00049, T-00053, and T-00059 remain valid; T-00050 through T-00052, T-00054, and T-00058 are rewritten.

These decisions are recorded in
[ADR 0024](../../adr/0024-framework-adapter-support-and-delivery-boundaries.md).

## Resolution

The grilling frontier is empty and the complete decision is accepted in
[ADR 0024](../../adr/0024-framework-adapter-support-and-delivery-boundaries.md), supported by the five linked
framework and PSR research notes. The framework catalog, standards-first reuse, native-adapter trials, async
delivery boundary, service-container activation, optional dependency policy, 1.2/1.3/2.0 release boundaries,
support evidence, and existing-ticket consequences are reconciled into PRD-00014 and PRD-00015. The approved
implementation DAG rewrites T-00050 through T-00052, T-00054, and T-00058; preserves T-00049, T-00053, and
T-00059; and publishes T-00069 through T-00075. The next flow is `/ask-matt` to select the first ready ticket.
