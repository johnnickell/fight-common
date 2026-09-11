# Define the portable AccessControl and persistence boundaries

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Specifications:** [PRD-00017 — Fight AccessControl Identity and Authentication Lifecycle](../../specs/00017-PRD.md), [PRD-00018 — Framework Starter Product and Walking-Slice Acceptance](../../specs/00018-PRD.md)
**Depends on:** [Define the package and repository ownership model](WF-010-package-and-repository-ownership.md), [Define the versioned HTTP, JSend, and presentation contracts](WF-011-versioned-http-jsend-and-presentation-contracts.md)

## Question

How can one AccessControl Domain and Application implementation remain usable through Doctrine,
Eloquent, Yii, CodeIgniter, and Slim without forcing every framework into one persistence model?

## Resolution

The repository is the persistence seam. Shared repository contracts return Fight AccessControl
aggregates and expose only domain-observable behavior. Each starter chooses its simplest natural
implementation:

- Symfony and Slim use Doctrine ORM with external XML mappings and may persist shared aggregates
  directly;
- Laravel keeps Eloquent models in Adapter and hydrates or saves shared aggregates;
- Yii keeps native database or ActiveRecord persistence models in Adapter and hydrates or saves
  shared aggregates; and
- CodeIgniter keeps Models or Query Builder records in Adapter and hydrates or saves shared
  aggregates.

Do not introduce abstract User, Role, or Permission base classes merely to support framework-specific
subclasses. That design conflicts with single-inheritance Active Record models and makes persistence
concerns part of the public domain hierarchy.

The shared User does not implement framework authentication interfaces. Each starter supplies a thin
native principal or identity provider that carries or resolves shared user identity. Fight
AccessControl remains the source of truth for passwords, account state, roles, permissions, session
grants, and revocation. Framework security libraries provide request, cookie, middleware, or provider
mechanics without becoming a competing identity store.

Small, contained persistence knowledge in shared aggregates is acceptable when it keeps the
implementation materially simpler. Existing Doctrine Collections may remain internal if the
extraction proves that `doctrine/collections` is a tolerable portable dependency; public collection
returns continue to use Fight Common collections or presentation data. Symfony Security dependencies
and token-storage access must move behind Application contracts.

Command handlers depend on Fight Common `UnitOfWork`. Each starter should bind it to a native
transaction boundary and prove atomic commit and rollback outcomes. The contract does not require
Eloquent, Yii, or CodeIgniter to emulate Doctrine's identity map or flush mechanics. If a prototype
cannot satisfy the existing abstraction naturally, adjust the smallest shared contract rather than
building an unnatural adapter.

Repository verification remains pragmatic. Each starter initially owns idiomatic integration tests
against its actual stack and database setup. Reusable conformance cases may be extracted only after
stable duplication proves valuable; no shared suite may force unsupported locking, concurrency, or
identity-map mechanics merely for parity.
