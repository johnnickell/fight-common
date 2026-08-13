# Fight Framework Portability and Starter Projects

**Label:** `wayfinder:map`
**Status:** Open

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
- one opinionated, replaceable default stack per starter, a complete editable `/client` React
  application, robust agent guidance, exact quality gates, and self-contained documentation.

The way is clear when the remaining tickets have selected the exact supported dependency ranges,
proved the persistence and transaction seams, specified the modern session and optional JWT flows,
audited every public Application contract, and produced repository-owned implementation handoffs.

## Notes

- This is a planning-only Wayfinder. Do not implement adapters, extract packages, create public
  repositories, or publish releases while resolving this map.
- Fight Common is the temporary umbrella planning authority. Once another repository exists, it
  becomes canonical for its own implementation plan, releases, and documentation.
- Existing `project`, Fight CMS, Omphalos, and the authentication boundary in StageOne Portal are
  evidence. Proprietary StageOne business and NFT behavior is excluded.
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
  planning authority.
- [Define the versioned HTTP, JSend, and presentation contracts](tickets/WF-011-versioned-http-jsend-and-presentation-contracts.md)
  fixed `/api/v1/{capability}`, adapter-only HTTP versioning, typed JSend payloads, `ResultSet`
  collection data, pure named presentation constructors, and framework-specific response adapters.
- [Define the portable AccessControl and persistence boundaries](tickets/WF-012-access-control-and-persistence-boundaries.md)
  fixed framework-neutral principals, aggregate-oriented repository contracts, native record
  adapters for Active Record frameworks, Doctrine XML mapping for Symfony and Slim, portable query
  read models, and pragmatic transaction equivalence.
- [Define starter product, governance, and documentation standards](tickets/WF-013-starter-product-governance-and-documentation.md)
  fixed the editable `/client`, HTTP-only authentication UI, native SPA host templates, one
  database-portable migration history, safe administrator bootstrap, catalog reconciliation,
  complete documentation, and strict agent-ready quality gates.
- [Audit Fight Common contracts and the 1.2 compatibility envelope](tickets/WF-014-fight-common-contract-and-compatibility-audit.md)
  fixed the authoritative 404-declaration audit, exact Scheduler `1.x` repair, neutral typed JSend
  envelope, honest `Arrayable` and `ResultSet` shapes, capability-first adapter namespaces, 32
  additive namespace migrations, in-repository framework fixtures, blocking `1.2.0` certification
  evidence, and the downstream capability worksheet. These decisions are now synthesized in
  [PRD-00014 — Fight Common Contract Repair and Compatibility Certification](../specs/00014-PRD.md)
  under [EPIC-00004 — Framework Portability and Starter Projects](../epics/00004-EPIC.md) and split into
  T-00047 through T-00056. The framework-fixture slice remains `needs-info` until WF-015 supplies its exact
  dependency and composition handoff.

## Frontier

1. [Select supported framework lines and default capability compositions](tickets/WF-015-framework-lines-and-default-capability-compositions.md)
   — select exact maintained dependency ranges, resolve isolated and combined Composer sets, and
   choose the native, portable, or package-backed composition for every capability in all five frameworks.
2. [Specify the Fight AccessControl extraction and authentication model](tickets/WF-016-access-control-extraction-and-authentication-model.md)
   — define the shared Domain and Application extraction, modern session and optional JWT behavior,
   and the framework-neutral security boundaries required by every starter.

## Waiting

- [Prove persistence, UnitOfWork, and walking-slice portability](tickets/WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md)
  waits on the framework compositions and AccessControl specification.
- [Synthesize full-support implementation handoffs](tickets/WF-018-full-support-implementation-handoffs.md)
  waits on the walking-slice prototypes and closes the map with repository-owned plans.

Choose only one frontier ticket per Wayfinder session. A ticket is takeable when every item in its
`Depends on` field is closed and it is not claimed by another session.

## Not yet specified

- Exact current-and-previous maintained version constraints that resolve together in Fight Common.
- Exact native or third-party default for every capability in every framework, especially queues,
  scheduling, templating, and persistence in the smaller stacks.
- Exact AccessControl extraction changes needed to remove Symfony security dependencies and contain
  Doctrine Collections.
- Session-grant schema, refresh-token family and replay rules, CSRF policy, and frontend refresh
  concurrency behavior for the optional stateless profile.
- Prototype evidence for Eloquent, Yii, and CodeIgniter aggregate hydration and native transactions.
- Final repository names, licenses, Packagist metadata, branch protections, and release automation.
- Remaining PRDs and executable ticket slices for framework composition, Fight AccessControl, persistence
  prototypes, and each starter.

## Out of scope

- Implementing adapters, moving source, or creating repositories during Wayfinding.
- Opening Fight CMS, Omphalos, StageOne, or any other existing proprietary project.
- Copying or publishing proprietary StageOne NFT or business-domain behavior.
- Forcing all frameworks to use Doctrine, Twig, Symfony Messenger, Symfony Security, or one shared
  container when a native facility provides the simpler composition.
- Bundling a Symfony Fight Common bundle; Symfony projects own service loading, autoconfiguration,
  compiler-pass registration, aliases, and environment configuration.
- Sharing the React application as a runtime package. Each starter receives complete editable source.
- Requiring implementation-mechanism parity across repositories or testing every replaceable adapter
  combination.
