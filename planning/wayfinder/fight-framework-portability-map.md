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

The planning route is complete: the exact supported dependency ranges, persistence and transaction seams,
modern session and JWT flows, public Application contract audit, permanent specifications, and Fight Common
umbrella ticket graph are resolved. Execution now transfers implementation authority into the six destination
repositories through T-00061 through T-00067.

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
  T-00047 through T-00056 plus T-00059 and T-00060. T-00055 is closed `wontfix`; framework testing stays in
  the real starter repositories rather than nested Fight Common fixtures.
- [Select supported framework lines and default capability compositions](tickets/WF-015-framework-lines-and-default-capability-compositions.md)
  fixed the current-only supported-line window with widen and tighten triggers, the exact Composer
  constraints per framework, five independent repository-owned compatibility lanes, the no-new-shared-adapter
  worksheet policy, the no-bundle starter-owned integration responsibilities, the one opinionated Slim stack,
  per-framework async and SPA-templating defaults, and a recommended Composer-installable composition for
  every capability (nothing is unsupported). These decisions are recorded in
  [ADR 0020](../adr/0020-supported-framework-lines-and-support-window.md) and
  [ADR 0021](../adr/0021-framework-default-capability-compositions.md) and synthesized in
  [PRD-00015 — Framework Supported Lines and Default Capability Compositions](../specs/00015-PRD.md),
  implemented in Fight Common through T-00057 and T-00058. T-00055 records the rejected nested-fixture plan.
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

## Frontier

No active Fight Common frontier remains. [WF-018](tickets/WF-018-full-support-implementation-handoffs.md) closed
after T-00061 through T-00067 established and verified the six repository-local authority transfers.

## Waiting

None.

Choose only one frontier ticket per Wayfinder session. A ticket is takeable when every item in its
`Depends on` field is closed and it is not claimed by another session.

## Transferred to repository-local planning

- Fight AccessControl owns capability-ticket creation from its local PRD-00001; T-00061 was accepted on
  2026-08-17.
- Each starter owns its adopted PRD-00018 product plan, executable vertical tickets, hosting, licensing,
  Packagist metadata, branch protections, release automation, and release state after its T-00062 through
  T-00066 public-source bootstrap handoff.

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
