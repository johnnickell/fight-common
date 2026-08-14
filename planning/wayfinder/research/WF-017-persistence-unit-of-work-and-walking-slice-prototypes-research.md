# WF-017 persistence, UnitOfWork, and walking-slice prototype research

**Research date:** 2026-08-13
**Scope:** Decision inputs for
[`WF-017`](../tickets/WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md). This note uses
primary framework documentation and the accepted Fight Common planning contracts. It does not install
frameworks, create starter repositories, change a public contract, or claim that documentation proves
runtime behavior.

## Executive finding

Every selected framework exposes enough documented composition machinery to attempt the accepted
AccessControl walking slice without moving framework concerns into Fight AccessControl. The evidence does
**not** yet prove that one unchanged Application implementation works in all five projects. Aggregate
hydration, relationship persistence, rollback, database portability, concurrent uniqueness, audit
atomicity, handler routing, refresh races, and private realtime authorization are executable prototype
questions.

The existing Fight Common `UnitOfWork` is plausible but not yet proven sufficient. Its callback transaction
method maps naturally to Doctrine, Laravel, Yii DB, and CodeIgniter transaction facilities. Its `commit()`
and `isClosed()` methods describe Doctrine's flush/EntityManager lifecycle more directly than native
record-oriented frameworks. A native adapter must not fake an identity map or make `commit()` silently
mean something different. WF-017 should first prove one application-owned callback transaction around the
complete mutation and required audit write. Only failed executable evidence may authorize the smallest
shared-contract change.

Doctrine XML remains the natural Symfony and Slim persistence experiment. Eloquent, Yii DB, and
CodeIgniter Model/Query Builder remain adapter-owned record-to-aggregate experiments; their framework
record/entity types must not leak through shared repositories or principals.

Realtime has a new version boundary. Laravel 13 documents Reverb-backed private channels and authorization,
but warns that queued broadcasts can run before a database transaction commits. Mercure's current Symfony
documentation describes private updates authorized by topic selectors, while the official Mercure 1.0
upgrade guide describes a breaking OAuth 2 `authorization_details` model. The Mercure prototype must pin a
hub, protocol, and integration version and prove one internally consistent authorization flow; it must not
combine examples from the two protocols.

## What primary sources establish

### Doctrine in Symfony and Slim

- Doctrine ORM 3.6 supports external XML mapping, including one mapping document per entity and explicit
  table and repository metadata. This permits mapping shared aggregate classes without attributes or a
  framework base class. [Doctrine ORM XML mapping](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/reference/xml-mapping.html)
- Doctrine queues writes until `EntityManager::flush()` and supports explicit transaction demarcation.
  Fight Common's current `DoctrineUnitOfWork` directly delegates `commit()` to `flush()` and
  `commitTransactional()` to `wrapInTransaction()`. [Doctrine transactions and concurrency](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/reference/transactions-and-concurrency.html)
- Symfony can bind interfaces, load services, and register tagged handlers in the project container; Slim
  can construct the same portable handlers through the already selected explicit PHP-DI composition.
  These facilities establish a viable registration seam, not the correctness of the full handler graph.
  [Symfony service container](https://symfony.com/doc/current/service_container.html),
  [Slim container resolution](https://www.slimframework.com/docs/v4/concepts/di.html)

**Prototype proof still required:** direct aggregate reconstitution with private/readonly state, relationship
mapping keyed only by `UserId`, optimistic/concurrent outcomes, one rollback boundary spanning mutation and
audit, PostgreSQL/MySQL migration parity, and explicit sync/async handler receipts.

### Laravel 13

- Laravel documents `DB::transaction(callable)` with automatic rollback on an exception and retry support
  for deadlocks. This is a direct candidate for `UnitOfWork::commitTransactional()`.
  [Laravel database transactions](https://laravel.com/docs/13.x/database#database-transactions)
- Eloquent models are framework records with relationships, casts, and model lifecycle behavior. The
  accepted boundary therefore remains an Adapter mapper/repository that converts records to and from
  shared aggregates; an Eloquent model must not become the shared aggregate.
  [Laravel Eloquent](https://laravel.com/docs/13.x/eloquent),
  [Laravel Eloquent relationships](https://laravel.com/docs/13.x/eloquent-relationships)
- Service providers register container bindings and event listeners; package discovery can discover a
  consumer-owned provider. Commands and queries still require explicit project routing so correctness is
  visible in the prototype rather than inferred from discovery.
  [Laravel service providers](https://laravel.com/docs/13.x/providers),
  [Laravel package discovery](https://laravel.com/docs/13.x/packages#package-discovery)
- Laravel broadcasting supports Reverb and private-channel authorization. Queued broadcasts created inside
  transactions may run before commit unless after-commit behavior is selected. This supports the accepted
  commit-then-publish rule and makes an after-commit receipt mandatory.
  [Laravel broadcasting and private channels](https://laravel.com/docs/13.x/broadcasting)

**Prototype proof still required:** mapper completeness for every aggregate and relationship, canonical-email
unique-index behavior under concurrent invitation and email-change reservations, transaction rollback,
database portability, after-commit dispatch, native principal resolution without shared-interface leakage,
and private-channel denial after session revocation.

### Yii 3

- `yiisoft/db` exposes query building, commands, and a callback transaction operation. It is sufficient to
  attempt repository-owned row mapping and a native transaction adapter without requiring Active Record.
  [Yii Database library](https://github.com/yiisoft/db)
- Yii configuration and DI packages provide project-owned definitions and service providers. Handler maps
  should remain explicit because the accepted async seam is intentionally provisional.
  [Yii configuration and service providers](https://yiisoft.github.io/docs/guide/concept/configuration.html),
  [Yii Config](https://github.com/yiisoft/config)
- Yii DB migration packages support database migration code, but documentation alone cannot prove that one
  migration produces equivalent PostgreSQL and MySQL constraints.
  [Yii DB Migration](https://github.com/yiisoft/db-migration)

**Prototype proof still required:** choose DB mapping or Active Record based on the smaller executable seam;
prove aggregate/relationship hydration, returned transaction values and rollback, unique constraints and
locking on both databases, explicit command/query/event maps, and invocation-neutral sync/async delivery.

### CodeIgniter 4

- CodeIgniter Models provide CRUD, Query Builder access, return-type conversion, validation, callbacks, and
  a supplied connection. The official guide also explicitly permits a repository-oriented application
  structure. This supports an Adapter repository but does not justify returning a CodeIgniter Entity as a
  shared aggregate. [CodeIgniter Models](https://codeigniter.com/user_guide/models/model.html),
  [CodeIgniter application structure](https://codeigniter.com/user_guide/concepts/structure.html)
- CodeIgniter supports automatic and manual transactions, strict mode, nested transaction tracking, and
  test mode. The docs warn that since 4.3 transaction exceptions are not thrown by default even with
  `DBDebug`; the adapter must convert failure status into a failed UnitOfWork outcome rather than report
  command success. [CodeIgniter transactions](https://codeigniter.com/user_guide/database/transactions.html)
- `Config\Services` provides project-owned shared service factories. Framework Events provide publish/
  subscribe hooks, but the portable bus routes must remain explicit and must not depend on incidental
  framework lifecycle events. [CodeIgniter services](https://codeigniter.com/user_guide/concepts/services.html),
  [CodeIgniter events](https://codeigniter.com/user_guide/extending/events.html)

**Prototype proof still required:** select Model or Query Builder from measured mapping complexity; prove
transaction failure detection, rollback, relationship writes, portable migrations, explicit bus routes,
principal/filter integration, and post-commit asynchronous publication.

### Private Mercure composition outside Laravel

- The Mercure protocol requires authorization before a subscriber receives a private update. Current
  Symfony integration documentation describes private `Update` instances and subscription authorization by
  a topic-matching credential. [Symfony Mercure integration](https://symfony.com/doc/current/mercure.html)
- The official hub defaults to disallowing anonymous subscribers in production and distinguishes publisher
  and subscriber credentials. [Mercure Hub installation](https://mercure.rocks/docs/hub/install),
  [Mercure Hub configuration](https://mercure.rocks/docs/hub/config)
- Mercure 1.0 changes authorization, match parameters, token claims, and cookies. This is a coordinated
  version boundary, not a detail a starter can leave implicit.
  [Mercure 1.0 upgrade guide](https://mercure.rocks/docs/UPGRADE)

**Prototype proof still required:** pin exact hub and PHP integration versions; disable anonymous access;
mint a short-lived, purpose-specific subscription credential from the authoritative current principal;
prove allowed and denied topics; prove denial of renewal after revocation; document the bounded lifetime of
an already-open connection; and publish only after durable command success.

## UnitOfWork decision input

The current contract is:

```php
interface UnitOfWork
{
    public function commit(): void;
    public function commitTransactional(callable $operation): mixed;
    public function isClosed(): bool;
}
```

The callback method is the portable center. The other two methods are risks:

| Method | Doctrine meaning | Native-record risk | Required prototype observation |
| --- | --- | --- | --- |
| `commit()` | Flush staged identity-map changes | A repository may already have executed SQL | No sensitive handler may write outside its required atomic boundary |
| `commitTransactional()` | Wrap callback and flush | Native APIs wrap a callback or manual transaction | Return value, rollback, nested call, and exception behavior match the Application contract |
| `isClosed()` | EntityManager unusable after failure | Connections usually remain reusable | No fake lifecycle state is required by portable Application code |

Recommendation for the prototype: make the use-case transaction boundary explicit and execute the entire
mutation plus required secret-free audit write inside `commitTransactional()`. Do not call native repository
writes first and then attempt to make them atomic with a later `commit()`. If all native adapters can satisfy
that behavior and portable Application code does not need `isClosed()`, retain the 1.x contract for
compatibility and record cleanup for 2.0. If any adapter requires buffering, false lifecycle state, or
framework branching in Application code, capture the failing test before considering a narrow replacement
such as a transaction-executor port. Research alone does not authorize that change.

## Bounded prototype evidence matrix

Each framework lane should use a disposable project and emit a machine-readable receipt plus focused test
output. A documentation citation is not a substitute for a receipt.

| Evidence lane | Symfony | Laravel | Yii 3 | CodeIgniter 4 | Slim |
| --- | --- | --- | --- | --- | --- |
| Aggregate persistence | Doctrine XML | Eloquent mapper | DB/AR mapper experiment | Model/Query Builder experiment | Doctrine XML |
| Atomic mutation + required audit | Doctrine transaction | `DB::transaction` | Yii DB transaction | Manual/native transaction with explicit failure check | Doctrine transaction |
| Databases | PostgreSQL + MySQL/MariaDB | PostgreSQL + MySQL/MariaDB | PostgreSQL + MySQL/MariaDB | PostgreSQL + MySQL/MariaDB | PostgreSQL + MySQL/MariaDB |
| Handler registration | project container/tag maps | provider + explicit maps | config provider + explicit maps | `Config\Services` + explicit maps | PHP-DI definitions + explicit maps |
| Principal seam | Symfony provider/principal | Laravel guard/provider | Yii middleware/identity adapter | filter/service adapter | middleware/principal adapter |
| Private realtime | pinned Mercure protocol | Reverb private channel | pinned Mercure protocol | pinned Mercure protocol | pinned Mercure protocol |

The common behavior suite must prove, at minimum:

1. Boot and migrate on both database families; schema identity relationships use `UserId`, and the
   canonical-email unique key has a documented future shape of `(tenant_id, canonical_email)`.
2. Hydrate and persist every aggregate and relationship without framework types crossing the repository
   boundary; rollback leaves neither mutation nor required audit record.
3. Concurrent invitation and email-change reservation produce one winner, preserve old-address authority
   until confirmation, release reservation on cancel/expiry, and revoke sessions on confirmation.
4. The unchanged handlers work through selected synchronous and asynchronous routes without an
   invocation-mode branch; post-commit delivery failure is reported after durable success.
5. Session listing/revocation, immediate token denial, cold-load refresh, ten-minute proactive refresh,
   single-flight concurrency, one bounded replayable retry, encrypted pending email, ciphertext destruction,
   and dispatch-failure recovery pass under the same public use cases.
6. Managed Role and Permission reconciliation uses `HashSet` difference, reports identical dry-run/apply
   plans, removes stale managed membership explicitly, and fails before all writes on any preflight error.
7. The HTTP walking slice returns the accepted JSend and `ResultSet` shapes and proves authorization for
   `POST /api/v1/access/session`, `GET /api/v1/access/session`, and `GET /api/v1/access/users`.
8. One authenticated private update is delivered only to its authorized session/topic; revocation blocks
   renewal immediately, and the receipt records any bounded residual open-connection window.

## Decisions still requiring grilling after prototypes

- Retain the existing `UnitOfWork`, add a compatible narrow port, or reserve a replacement for 2.0.
- Select Yii DB versus Active Record and CodeIgniter Model versus Query Builder from the smallest passing
  mapper and transaction evidence.
- Fix the exact Laravel adapter-event shape and the Yii async transport boundary.
- Pin Mercure protocol/integration versions and select the credential delivery mechanism per starter.
- Decide whether one migration source can remain portable or each native migration system needs equivalent
  project-owned definitions checked by schema receipts.
- Split the full walking slice into independently reproducible implementation tickets. The research note
  does not turn WF-017 into one build ticket.

## Research boundary

No prototype has run. No framework dependency has been installed. No database, queue, hub, browser client,
or concurrent request has been exercised. The sources establish available mechanisms and known hazards;
only the bounded executable lanes can establish portability.
