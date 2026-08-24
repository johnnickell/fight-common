# Fight Framework Portability and Starter Projects

**Label:** `wayfinder:map`
**Status:** Closed

## Destination

Produce an implementation-ready, cross-repository route for full Fight support in Symfony, Laravel,
Yii 3, CodeIgniter 4, and Slim. The route must preserve framework-neutral Domain and Application
layers while giving every framework one idiomatic, documented, and tested composition for every
public Fight Common Application contract.

The destination includes:

- additive Fight Common adapters targeted at `1.2.0` where compatibility permits, with an explicit
  `2.0.0` cleanup boundary;
- a public `johnnickell/fight-access-control` Composer package containing the shared identity,
  authentication, authorization, User, Role, and Permission Domain and Application layers;
- public `project-symfony`, `project-laravel`, `project-yii`, `project-codeigniter`, and
  `project-slim` Composer create-project and GitHub template repositories;
- identical `/api/v1/access` journeys and JSend representations implemented through native HTTP,
  security, persistence, routing, templating, queue, and container facilities where appropriate;
- authenticated private realtime journeys implemented through Laravel Reverb where native and a secured
  Mercure Hub composition elsewhere, behind a portable publication and subscription-authorization seam;
- one opinionated, replaceable default stack per starter, a complete editable `/client` React
  application, robust agent guidance, exact quality gates, and self-contained documentation.

The starter-repository handoff route is complete. The Fight Common adapter route is reopened because the
previous no-new-shared-adapter policy omitted reusable Laravel, Yii, and CodeIgniter integration and grouped
equivalent framework service-container extension points by the runtime capability they happened to wire.

**Done** = every linked Wayfinder decision is closed, the permanent decisions are linked to their epic, PRDs,
and executable-ticket handoff, and no charting question remains before `/ask-matt` returns to normal execution.

## Notes

- This is a planning-only Wayfinder. Do not implement adapters, extract packages, create public
  repositories, or publish releases while resolving this map.
- Fight Common is the temporary umbrella planning authority. Once another repository exists, it
  becomes canonical for its own implementation plan, releases, and documentation.
- Existing `project`, Fight CMS, and Omphalos are implementation evidence. Authentication decisions also
  use the approved product requirements and primary security sources.
- Full support means one documented and tested solution for every important contract, not one
  redundant framework-branded class per contract.
- Framework-native integration is preferred when it is natural. Portable Fight Common adapters are
  reused where framework independence is natural. A well-supported Composer package fills a real
  framework gap.
- Every starter owns one intentional default stack. Replaceable alternatives are composition-root
  choices, not an obligation to test every package combination.
- Refer to tickets by linked names rather than bare identifiers.
- Framework service-container extension points live together under `Adapter\ServiceContainer`, while the
  runtime implementations they register remain capability-first.

## Decisions so far

- [Establish the portability destination and release boundaries](tickets/WF-009-portability-destination-and-release-boundaries.md)
  fixed full consumer-journey support, the additive `1.2.0` target, the `2.0.0` cleanup boundary,
  in-package framework adapters, explicit supported-version ranges, and non-lockstep `0.x` starter
  incubation.
- [Define the package and repository ownership model](tickets/WF-010-package-and-repository-ownership.md)
  fixed the public Fight AccessControl package, five public starter repositories, portable shared
  layers, project-owned composition roots, and the transition from umbrella to repository-local
  planning authority. These decisions are synthesized in
  [PRD-00016 — Fight Package and Starter Repository Ownership](../specs/00016-PRD.md); WF-018 owns the
  repository-local implementation handoffs.
- [Define the versioned HTTP, JSend, and presentation contracts](tickets/WF-011-versioned-http-jsend-and-presentation-contracts.md)
  fixed `/api/v1/{capability}`, adapter-only HTTP versioning, typed JSend payloads, `ResultSet`
  collection data, pure named presentation constructors, and framework-specific response adapters. Fight
  Common response compatibility is permanent in [PRD-00014](../specs/00014-PRD.md); starter HTTP and client
  delivery are permanent in [PRD-00018](../specs/00018-PRD.md).
- [Define the portable AccessControl and persistence boundaries](tickets/WF-012-access-control-and-persistence-boundaries.md)
  fixed framework-neutral principals, aggregate-oriented repository contracts, native record
  adapters for Active Record frameworks, Doctrine XML mapping for Symfony and Slim, portable query
  read models, and pragmatic transaction equivalence. Shared behavior is permanent in
  [PRD-00017](../specs/00017-PRD.md); framework-owned persistence acceptance is permanent in
  [PRD-00018](../specs/00018-PRD.md).
- [Define starter product, governance, and documentation standards](tickets/WF-013-starter-product-governance-and-documentation.md)
  fixed the editable `/client`, HTTP-only authentication UI, native SPA host templates, one
  database-portable migration history, safe administrator bootstrap, Managed Role/Permission reconciliation,
  complete documentation, and strict agent-ready quality gates. These decisions are permanent in
  [PRD-00018](../specs/00018-PRD.md).
- [Audit Fight Common contracts and the 1.2 compatibility envelope](tickets/WF-014-fight-common-contract-and-compatibility-audit.md)
  fixed the authoritative 404-declaration audit, exact Scheduler `1.x` repair, neutral typed JSend
  envelope, honest `Arrayable` and `ResultSet` shapes, capability-first adapter namespaces, 32
  additive namespace migrations, exported-package consumer probes, blocking `1.2.0` certification
  evidence, and the downstream capability worksheet. These decisions are now synthesized in
  [PRD-00014 — Fight Common Contract Repair and Compatibility Certification](../specs/00014-PRD.md)
  under [EPIC-00004 — Framework Portability and Starter Projects](../epics/00004-EPIC.md) and split into
  T-00047 through T-00056, T-00059, T-00060, and T-00069. T-00055 is closed `wontfix`; framework testing stays
  in the real starter repositories rather than nested Fight Common fixtures.
- [Select supported framework lines and default capability compositions](tickets/WF-015-framework-lines-and-default-capability-compositions.md)
  fixed the current-only supported-line window with widen and tighten triggers, the exact Composer
  constraints per framework, five independent repository-owned compatibility lanes, the no-new-shared-adapter
  worksheet policy, the no-bundle starter-owned integration responsibilities, the one opinionated Slim stack,
  per-framework async and SPA-templating defaults, and a recommended Composer-installable composition for
  every capability (nothing is unsupported). These decisions are recorded in
  [ADR 0020](../adr/0020-supported-framework-lines-and-support-window.md) and the portions of
  [ADR 0021](../adr/0021-framework-default-capability-compositions.md) not superseded by ADRs 0023 and 0024.
  [PRD-00015 — Framework Adapter Support and Capability Composition](../specs/00015-PRD.md) is implemented in
  Fight Common through T-00057, T-00058, and T-00070 through T-00075. T-00055 records the rejected nested-fixture
  plan.
- [Specify the Fight AccessControl extraction and authentication model](tickets/WF-016-access-control-extraction-and-authentication-model.md)
  fixed the Domain/Application-only package boundary, invitation and account-state model, Managed
  Role/Permission reconciliation, multi-device shared sessions, hardened access-JWT/refresh behavior,
  invocation-neutral delivery handlers, required security-audit durability, private realtime authorization,
  and the complete starter/client security profile. The decisions are recorded in
  [ADR 0022](../adr/0022-invited-registration-and-multi-session-jwt-authentication.md) and supported by the
  linked research note. Shared AccessControl behavior is permanent in
  [PRD-00017](../specs/00017-PRD.md), while framework delivery is permanent in
  [PRD-00018](../specs/00018-PRD.md).
- [Prove persistence, UnitOfWork, and walking-slice portability](tickets/WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md)
  proved the risky persistence, transaction, composition, principal, HTTP, JWT, refresh, realtime, queue,
  and client-contract seams through bounded disposable evidence. Its decision record and Git evidence ledger
  are retained; nested prototype projects were removed after closure. Booted framework applications and
  browser/runtime acceptance now belong to the real destination repositories through WF-018. Its shared
  Fight Common consequences are permanent in [PRD-00014](../specs/00014-PRD.md), AccessControl consequences in
  [PRD-00017](../specs/00017-PRD.md), and starter acceptance in [PRD-00018](../specs/00018-PRD.md).
- [Synthesize full-support implementation handoffs](tickets/WF-018-full-support-implementation-handoffs.md)
  completed the Fight Common specification and umbrella-ticket layer. PRD-00014 through PRD-00016 own the
  Fight Common work and T-00047 through T-00067 graph. PRD-00017 and PRD-00018 intentionally produce no
  detailed Fight Common tickets: Fight AccessControl adopts the former as repository-local PRD-00001, and each
  starter adopts the relevant latter contract through T-00062 through T-00066 before creating local tickets.
  WF-018 is closed after execution and verification of those six authority transfers.
- [Define the service-container and framework-adapter namespace model](tickets/WF-019-service-container-and-adapter-namespace-model.md)
  reopened the Fight Common adapter route, grouped equivalent framework wiring under `Adapter\ServiceContainer`,
  retained capability-first runtime adapters, selected neutral `Application\Http` and
  `Application\ServiceContainer` paths, and preserved additive `1.2.0` compatibility with `2.0.0` cleanup.
  [ADR 0023](../adr/0023-service-container-and-framework-adapter-namespaces.md) records the decision and
  supersedes the conflicting portions of ADRs 0019 and 0021.
- [Research Symfony, Slim, and standalone adapter seams](tickets/WF-023-symfony-slim-and-standalone-adapter-seams.md)
  separated Symfony-native runtime adapters, service-container compiler passes, neutral message consumers,
  PSR-based Slim integrations, and reusable provider adapters. It also rejected Bernard from the supported
  queue matrix and retained Monolog through PSR-3 rather than a Fight-branded logger wrapper.
- [Research Laravel-native adapter seams](tickets/WF-020-laravel-native-adapter-seams.md)
  selected queued command Jobs and queued event listeners that delegate to neutral Fight message consumers,
  a narrow transactional UnitOfWork, capability-specific opt-in service providers, targeted Illuminate
  translation adapters, and provider-neutral reuse where Laravel adds no distinct contract.
- [Research CodeIgniter-native adapter seams](tickets/WF-022-codeigniter-native-adapter-seams.md)
  selected a native transactional UnitOfWork and stable official Queue integration as the first shared
  adapters, explicit starter `Config\Services` delegation, targeted HTTP/routing/mail prototypes, and reuse of
  neutral providers for the remaining capability surface.
- [Research Yii-native adapter seams](tickets/WF-021-yii-native-adapter-seams.md)
  selected capability-scoped providers, Yii DB transactions, Yii routing, conditional mail/view adapters,
  a shared PSR HTTP lane, and provider-neutral reuse. Yii Queue remains an experimental adapter candidate
  until its core and a production broker adapter publish stable compatible releases.
- [Research PSR interoperability and adapter seams](tickets/WF-025-psr-interoperability-and-adapter-seams.md)
  established the standards-first lane: direct use for honest inward interfaces, shared PSR-15 middleware and
  PSR-17 JSend response creation, canonical PSR-6 and PSR-16 cache adapters, and a synchronous outward PSR-18
  client view. PSR-14 remains separate from Fight messaging, PSR-18 does not satisfy Fight's async client
  contract in the reverse direction, and standards without an existing capability do not receive speculative
  adapters.
- [Select the framework-adapter support matrix](tickets/WF-024-framework-adapter-support-matrix.md)
  accepted the complete shared, Symfony, Laravel, Yii, CodeIgniter, and Slim adapter catalog; native-first
  conformance policy; async delivery and Yii Queue release boundaries; capability-scoped framework and Fight
  container registration; optional development/suggest dependencies; dual Fight/PSR-18 HTTP-client wiring;
  two-key support evidence; and additive 1.2 with a possible 1.3 before 2.0 cleanup. [ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md)
  records the decision. PRD-00014 and PRD-00015 now hold the permanent specification, and T-00050 through
  T-00054, T-00058, and T-00069 through T-00075 hold the reconciled implementation graph.

## Tickets

| Ticket | Type | Mode | Status | Depends On |
|---|---|---|---|---|
| [Establish the portability destination and release boundaries](tickets/WF-009-portability-destination-and-release-boundaries.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Define the package and repository ownership model](tickets/WF-010-package-and-repository-ownership.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Define the versioned HTTP, JSend, and presentation contracts](tickets/WF-011-versioned-http-jsend-and-presentation-contracts.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Define the portable AccessControl and persistence boundaries](tickets/WF-012-access-control-and-persistence-boundaries.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Define starter product, governance, and documentation standards](tickets/WF-013-starter-product-governance-and-documentation.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Audit Fight Common contracts and the 1.2 compatibility envelope](tickets/WF-014-fight-common-contract-and-compatibility-audit.md) | Research / Domain Modeling | HITL | **Closed** | — |
| [Select supported framework lines and default capability compositions](tickets/WF-015-framework-lines-and-default-capability-compositions.md) | Research / Domain Modeling | HITL | **Closed** | Contract audit |
| [Specify the Fight AccessControl extraction and authentication model](tickets/WF-016-access-control-extraction-and-authentication-model.md) | Research / Domain Modeling | HITL | **Closed** | — |
| [Prove persistence, UnitOfWork, and walking-slice portability](tickets/WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md) | Research / Domain Modeling | HITL | **Closed** | — |
| [Synthesize full-support implementation handoffs](tickets/WF-018-full-support-implementation-handoffs.md) | Grilling / Domain Modeling | HITL | **Closed** | Prior portability decisions |
| [Define the service-container and framework-adapter namespace model](tickets/WF-019-service-container-and-adapter-namespace-model.md) | Grilling / Domain Modeling | HITL | **Closed** | Contract audit |
| [Research Laravel-native adapter seams](tickets/WF-020-laravel-native-adapter-seams.md) | Research | AFK | **Closed** | Service-container model |
| [Research Yii-native adapter seams](tickets/WF-021-yii-native-adapter-seams.md) | Research | AFK | **Closed** | Service-container model |
| [Research CodeIgniter-native adapter seams](tickets/WF-022-codeigniter-native-adapter-seams.md) | Research | AFK | **Closed** | Service-container model |
| [Research Symfony, Slim, and standalone adapter seams](tickets/WF-023-symfony-slim-and-standalone-adapter-seams.md) | Research | AFK | **Closed** | Service-container model |
| [Select the framework-adapter support matrix](tickets/WF-024-framework-adapter-support-matrix.md) | Grilling / Domain Modeling | HITL | **Closed** | Framework and PSR research |
| [Research PSR interoperability and adapter seams](tickets/WF-025-psr-interoperability-and-adapter-seams.md) | Research | AFK | **Closed** | Service-container model |

## Blocking relationships

```text
Contract audit ──→ Service-container model ──→ Framework and PSR research ──→ Support matrix

Destination + ownership + HTTP + persistence + governance + audit + supported lines
  + AccessControl + walking-slice evidence + support matrix ──→ Implementation handoff
```

## Implementation Handoff

- Fight AccessControl owns capability-ticket creation from its local PRD-00001; T-00061 was accepted on
  2026-08-17.
- Each starter owns its adopted PRD-00018 product plan, executable vertical tickets, hosting, licensing,
  Packagist metadata, branch protections, release automation, and release state after its T-00062 through
  T-00066 public-source bootstrap handoff.

## Frontier

None. The map's decision frontier and specification/ticket handoff are closed; the next flow is `/ask-matt`.

## Not yet specified (fog)

None within this Wayfinder. Future framework support or product capabilities start a new map through
`/wayfinder`; they do not reopen a completed handoff without a new decision boundary.

## Out of scope

- Implementing adapters, moving source, or creating repositories during Wayfinding.
- Opening Fight CMS, Omphalos, or unrelated proprietary projects during implementation.
- Copying or publishing external proprietary business-domain behavior.
- Forcing all frameworks to use Doctrine, Twig, Symfony Messenger, Symfony Security, or one shared
  container when a native facility provides the simpler composition.
- Bundling a Symfony Fight Common bundle; Symfony projects own service loading, autoconfiguration,
  compiler-pass registration, aliases, and environment configuration.
- Sharing the React application as a runtime package. Each starter receives complete editable source.
- Requiring implementation-mechanism parity across repositories or testing every replaceable adapter
  combination.
