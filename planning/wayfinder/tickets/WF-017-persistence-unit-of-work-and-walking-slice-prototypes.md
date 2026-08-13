# Prove persistence, UnitOfWork, and walking-slice portability

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Select supported framework lines and default capability compositions](WF-015-framework-lines-and-default-capability-compositions.md), [Specify the Fight AccessControl extraction and authentication model](WF-016-access-control-extraction-and-authentication-model.md)

## Question

Can the accepted repository, principal, transaction, HTTP, and composition seams support one unchanged
AccessControl Domain and Application walking slice in every framework without unnatural machinery?

## Must decide

- prototype boundaries and disposable evidence for Doctrine XML persistence in Symfony and Slim;
- Eloquent record-to-aggregate hydration, relationship persistence, transactions, and identity
  integration in Laravel;
- Yii database or ActiveRecord record-to-aggregate hydration and native transaction integration;
- CodeIgniter Model or Query Builder record-to-aggregate hydration and native transaction integration;
- whether the existing Fight Common `UnitOfWork` contract is sufficient or needs the smallest additive
  or major-version adjustment;
- portable migrations across PostgreSQL and MySQL or MariaDB for each native migration system;
- exact explicit or generated command, query, and event handler registration per framework;
- framework-native principal/provider integration without shared aggregate interface leakage; and
- one end-to-end walking slice covering boot, migration, repository, command/query dispatch,
  `POST /api/v1/access/session`, `GET /api/v1/access/session`, `GET /api/v1/access/users`, JSend,
  `ResultSet`, authorization, SPA shell, and functional tests.

## Resolution boundary

Produce bounded prototype evidence and decisions, not polished starter implementations. If a seam
fails, prefer the simplest framework implementation and revise the smallest shared abstraction. Do
not force mechanical parity with Doctrine or promote experimental prototypes as supported releases.
