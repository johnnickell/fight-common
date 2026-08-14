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
- secure realtime publication and subscription prototypes: private-update support through the portable
  socket port, Mercure hub/container and subscription authorization for Symfony, Yii, CodeIgniter, and
  Slim, and native Laravel Reverb broadcasting with private-channel authorization unless prototype
  evidence selects a safer or simpler composition; and
- one end-to-end walking slice covering boot, migration, repository, command/query dispatch,
  `POST /api/v1/access/session`, `GET /api/v1/access/session`, `GET /api/v1/access/users`, JSend,
  `ResultSet`, authorization, one authenticated private realtime update, SPA shell, and functional tests.

The authentication prototype must also prove two simultaneous sessions for one user, self-service listing
and remote revocation, super-admin revocation through `MANAGE_USER_SESSIONS`, immediate access-token denial
after revocation, audit evidence for the administrative action, cold-load refresh bootstrap, proactive
ten-minute refresh, single-flight concurrency, one bounded replayable retry, encrypted pending email
delivery, successful ciphertext destruction, and recovery of pending work after dispatch failure.

For each classified sensitive command in the walking slice, failure to persist its required audit record
must prevent command success. Each consumer framework project must prove either one atomic UnitOfWork or an
atomic durable audit handoff when its selected audit and mutation stores cannot share a transaction.

Persistence prototypes must enforce canonical-email uniqueness across every account state under concurrent
invitations, keep all relationships keyed by `UserId`, and isolate the unique index so its documented
multi-tenant evolution is `(tenant_id, canonical_email)` rather than an identity rewrite.

The authentication prototype must also prove email-address reservation under concurrency, old-address
authority before confirmation, reservation release after cancellation/expiry, global session revocation
after confirmation, current-password proof for self-service initiation, audited super-admin
initiation/cancellation, old-mailbox notices, one-grant/one-reservation limits, throttling, and the absence of
any implicit Role hierarchy or `ROLE_` prefix behavior.

Fight AccessControl contributes Domain/Application ports and shared behavioral conformance tests only. The
prototype implementations for database or Redis sessions, persistence, HTTP, JWT, email, templates, and
realtime are owned by the consumer framework projects; no adapter is added to the AccessControl package.
AccessControl may use test-only `InMemoryRepositories`, and the prototypes must prove that the same command
handlers and event subscribers work under project-selected synchronous and asynchronous invocation without
invocation-mode branches in Application services.

Reconciliation prototypes use Fight Common `HashSet` difference to compute exact Managed Role membership,
prove dry-run/apply plan parity and all-or-nothing preflight, and include explicit Managed Permission removal.
Authentication prototypes exercise the configured 72-hour activation, one-hour reset, and 24-hour
email-change defaults and apply the common behavior suite to any optional native cookie-session profile.

Account-state tests prove disabled-user reset without enablement or session creation, active-only email
change with disablement cancellation, and atomic pending-invitation correction that revokes the predecessor
grant/delivery and issues an unrelated fresh credential.

## Resolution boundary

Produce bounded prototype evidence and decisions, not polished starter implementations. If a seam
fails, prefer the simplest framework implementation and revise the smallest shared abstraction. Do
not force mechanical parity with Doctrine or promote experimental prototypes as supported releases.
