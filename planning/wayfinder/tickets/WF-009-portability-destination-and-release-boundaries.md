# Establish the portability destination and release boundaries

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Specifications:** [PRD-00014 — Fight Common Contract Repair and Compatibility Certification](../../specs/00014-PRD.md), [PRD-00015 — Framework Adapter Support and Capability Composition](../../specs/00015-PRD.md), [PRD-00016 — Fight Package and Starter Repository Ownership](../../specs/00016-PRD.md), [PRD-00018 — Framework Starter Product and Walking-Slice Acceptance](../../specs/00018-PRD.md)
**Depends on:** None

## Question

What does full Laravel, Yii 3, CodeIgniter 4, Slim, and Symfony support mean, where do the adapters
live, and which Fight Common release may contain the work?

## Resolution

Full support is measured by complete consumer journeys and Application-contract coverage, not equal
class counts. Every framework must have documented wiring for messaging, persistence, transactions,
HTTP, routing, URL generation, templating, authentication, queues, mail, SMS, storage, validation,
caching, process execution, scheduling, and observability. Native framework facilities are adapted
where useful; portable Fight Common adapters remain portable; selected Composer packages fill real
gaps.

All Fight Common adapters remain in `johnnickell/fight-common`. Dependencies required to test those adapters
stay in root `require-dev`, and optional activation packages are named in `suggest` for consumers. Each starter
owns its framework dependency graph and verification. The project accepts responsibility for its shipped
adapters rather than splitting them into separate framework packages.

Supported framework versions use explicit Composer ranges. The intended policy is the current and
previous maintained line where those versions are secure and mutually resolvable. Verification uses lowest
and latest compatible lanes in each owning starter repository; Fight Common does not build a combined starter
project. New framework majors are adopted deliberately rather than accidentally widening constraints.

Fight Common `1.2.0` is the preferred target only while changes remain additive:

- add new adapters and typed JSend support;
- introduce framework-qualified namespaces;
- retain deprecated namespace and raw-array compatibility shims;
- preserve public constructors and contracts or restore their compatibility;
- add documentation, suggestions, and framework test lanes.

Fight Common `2.0.0` removes deprecated namespaces, requires typed JSend data, normalizes contracts
that cannot be repaired additively, and adopts justified breaking dependency or constructor changes.
The existing Scheduler required-constructor change is a known compatibility concern and must be
resolved before `1.2.0` certification.

Fight AccessControl and the five starter repositories begin with solid `0.x.y` releases rather than
beta suffixes. They reach `1.0.0` only after their contracts, complete capability support,
documentation, security, and quality gates are stable. Their version numbers do not remain locked to
Fight Common or to one another.
