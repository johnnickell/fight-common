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

## Accepted prototype decisions

**Shared understanding confirmed:** 2026-08-13

- Prove the walking slice through five independently reproducible framework projects: Symfony, Laravel,
  Yii, CodeIgniter, and Slim. Each project runs the same framework-neutral behavioral contract and emits
  machine-readable receipts. WF-017 remains the parent decision ticket until all five projects can be
  compared; it is not one monolithic prototype implementation.
- Give each framework project its own repository. A repository may remain private during initial testing
  and become public when it is ready. Feature branches merge into `develop`, which is the integration and
  normal pull-request target; `main` is the more stable branch between releases. Merging to `main` does not
  require a version tag. Versions remain absent or non-authoritative until the project is ready to tag an
  intentional pre-1.0 release such as `0.1.0` or `0.2.0`.
- Begin each framework project with one bounded horizontal bootstrap ticket: render a tested Hello World
  page, then establish project configuration and service composition without CQRS or database interaction.
  After that bootstrap is green, build one user-valued vertical slice per use-case ticket. Each slice crosses
  its required UI or HTTP, Application, persistence, and response boundaries and remains green as later
  slices are added. Do not wait for the complete authentication capability set before recording a discovered
  seam failure.
- Treat every individual user-valued use case as one vertical slice and normally one canonical use-case
  ticket. Horizontal bootstrap and cross-cutting technical hardening remain explicitly named enabling
  tickets; do not disguise them as user stories. Apply this rule to the accepted AccessControl command and
  query surface without reopening the decomposition case by case; grill only a genuinely ambiguous boundary
  or architectural trade-off.
- Make `/` an intentionally designed public introduction to the starter rather than a generic framework
  splash screen. Anonymous and authenticated visitors share the same documentation-oriented shell; an
  authenticated visitor additionally sees signed-in state and links to available application journeys, and
  login redirects back to `/`.
- Give every starter a polished public `/docs` experience implemented with its framework's presentation
  facilities rather than MkDocs. It explains what the starter is, current implementation status, local setup,
  architecture, package and framework-documentation links, routes and capabilities, quality commands, and
  repository documentation. As slices become available, it links to their working journeys. Fake credentials
  may appear only in the local development environment.
- Design the documentation as an approachable bridge between the selected framework's established way of
  working and Fight's CQRS and Hexagonal Architecture conventions. The result should feel like one coherent,
  opinionated full-stack development experience while remaining honest about which behavior belongs to the
  framework and which belongs to Fight packages and starter composition.
- Isolate public documentation templates and their thin controller and route wiring so a new project can
  remove the public documentation surface without disturbing application behavior. Include a tested "Remove
  the public documentation and start your application" guide that names the deletable paths and any small
  route-registration edit.
- Keep durable reference prose in repository-owned Markdown or structured content under `/docs` and render
  it through polished framework-native templates. Removing the public controller, routes, and templates does
  not delete that reference material. The runtime documentation renderer accepts trusted repository content,
  not user-authored Markdown.
- Keep documentation executable: commands, routes, configuration, and examples refer to real project
  contracts and tested example code. Generate displayed facts from authoritative project metadata where
  practical instead of maintaining independent prose copies.
- Verify the documented public-docs removal recipe in a disposable project copy. Apply the named deletions
  and route edit, boot the application, and rerun its required tests so removability is demonstrated rather
  than inferred.
- Use a shared Fight starter identity, information architecture, accessibility baseline, and design quality
  across all five projects while allowing framework-specific typography, accents, examples, and terminology.
  The projects are visibly related without being mechanical visual clones.
- Keep the complete editable React administration and user-management application in `/client`. Mount its
  built SPA at a configurable route whose starter default is `/admin`; a developer can change that route to
  `/app`, `/portal`, or another path without changing Application behavior. The framework-native controller
  serves only the SPA shell and client-side route fallback; it performs no use case, authorization decision,
  or JSON response work. JSON HTTP actions remain the backend boundary, and `/` plus `/docs` remain the
  public documentation experience. The exact per-framework source of truth—project configuration,
  environment input, generated client build configuration, or another native mechanism—remains a prototype
  question. Each project must prove its chosen mechanism with an alternate-mount executable test rather than
  assuming all five routing and asset systems compose identically.
- Treat a richer Fight Common documentation experience as a separate follow-up opportunity. WF-017 records
  the observation but does not expand its framework-portability prototypes into a Fight Common documentation
  redesign.
- Use the same ordered use-case tickets, shared Application implementation, user stories, and acceptance
  behavior in all five framework projects. Framework projects may differ only in adapters, migrations,
  composition, and UI plumbing needed to expose the same behavior; framework conveniences do not redefine
  a use case.
- Make login the first vertical slice after bootstrap: an active user enters an email address and password,
  submits the login form through `POST /api/v1/access/session`, and reaches the home page with a visible
  authenticated state. The slice includes command handling, user persistence, password verification,
  session creation, SPA interaction, redirect, and focused acceptance tests. `EmailAddress` remains the
  login identity; the prototypes do not introduce a separate username concept.
- Follow login with a cold-load session-restoration slice. Refreshing or reopening the SPA uses the HttpOnly
  refresh credential to restore authenticated state while the access JWT remains only in client memory.
- Implement current-session logout as its own slice. It revokes only the current server-side refresh session,
  clears browser credentials, and returns the UI to unauthenticated state without revoking other sessions.
- Implement active-session management next. With two simultaneous sessions for one user, list both using
  coarse device information, remotely revoke the other session, deny its next request immediately, and keep
  the current session active. Super-admin revocation remains a later independently audited slice.
- Follow session management with an authorized user-listing slice. An administrator with the required
  permission uses `GET /api/v1/access/users` to view a paginated user list through query dispatch,
  repository pagination, `ResultSet`, JSend, and a functional administrative screen; unauthorized users are
  denied.
- Add super-admin session revocation as the next slice. From the user journey, `ROLE_SUPER_ADMIN` with
  `MANAGE_USER_SESSIONS` inspects another user's sessions and revokes one with non-secret reason or context.
  Revocation and its required audit record commit atomically, the revoked session is denied immediately,
  and an ordinary administrator remains unauthorized.
- Complete the pre-email sequence with one session-continuity hardening slice. It proves proactive refresh,
  single-flight refresh concurrency, and one bounded replayable retry as one user outcome: authenticated work
  continues safely while the short-lived access token rotates. Each behavior retains focused race and failure
  tests inside that slice.
- Begin email-backed journeys with an authorized invitation slice. Atomically commit the new
  `PENDING_ACTIVATION` user, activation grant, encrypted pending-email delivery, and required audit record,
  then deliver the message to a Mailtrap test inbox for integration evidence and UAT. Mailtrap credentials
  are environment-owned secrets and never enter repository fixtures, logs, receipts, or public docs. A mail
  delivery failure does not erase the pending user or claim delivery succeeded.
- Implement account activation as a separate slice. The invited person follows the one-time link, chooses
  an accepted initial password, transitions to `ACTIVE`, and receives their first authenticated session.
  The consumed grant cannot be reused, and successful delivery destroys its retained ciphertext.
- Implement email-delivery recovery as the next slice rather than overloading either happy path. Prove that
  pending work remains discoverable after post-commit dispatch failure, retries safely, destroys expired
  ciphertext, and requires activation resend to revoke the predecessor and create an unrelated replacement
  grant and delivery.
- Keep the required automated suite independent of Mailtrap by using a deterministic in-memory or fake mail
  adapter. Add an opt-in Mailtrap integration check and use Mailtrap for human UAT so the real project mail
  composition is proven without making ordinary builds depend on an external service.
- Implement password-reset request as its own slice. The public endpoint always returns the same generic
  response. An eligible user receives a one-time reset link through the encrypted pending-delivery system;
  missing, deleted, or otherwise ineligible identities reveal no account state and receive no unauthorized
  delivery. An eligible disabled user may receive the reset journey governed by the completion behavior below.
- Implement password-reset completion as a separate slice. Consuming the one-time grant sets the new
  password, revokes every existing refresh session, advances authentication version, clears browser
  credentials, and requires a fresh login. A disabled user may reset the password but remains disabled and
  receives no session.
- Implement authenticated password change as its own slice. An active user proves the current password and
  chooses a new one; success revokes every refresh session including the current one, advances authentication
  version, clears browser credentials, and returns the user to login without issuing a reset grant.
- Implement email-address change request separately from confirmation. An active user proves the current
  password, requests an unclaimed canonical address, reserves that address, and receives a one-time
  confirmation message there while the old mailbox receives a security notice. The old address remains
  authoritative and existing sessions remain active until confirmation.
- Implement email-address confirmation as its own slice. Atomically replace the authoritative email address,
  consume the grant, release the reservation, advance authentication version, revoke every session, and
  require login with the new address. The old canonical address becomes available only after commit.
- Implement cancellation and expiry as one pending-email-change lifecycle slice. Either outcome consumes the
  pending grant and releases the reserved address without changing the authoritative email or active sessions.
  Enforce and test at most one pending grant and one reservation per user.
- Implement super-admin email-change assistance separately. A super administrator may initiate or cancel the
  same confirmation journey with a required non-secret reason and atomic audit record, but cannot bypass
  confirmation by the new mailbox. Ordinary administrators remain unauthorized.
- Implement account disablement as its own audited slice. An authorized administrator disables the user,
  revokes every session, advances authentication version, cancels any pending email-address change, releases
  its reservation, and immediately prevents access and login.
- Implement account enablement separately. An authorized administrator enables a disabled, non-deleted user
  without restoring an old session or creating a new one; the user authenticates normally afterward. Deleted
  users remain reachable only through the distinct audited restoration journey.
- Implement soft deletion and audited restoration as one account-lifecycle slice. Deletion revokes sessions
  and blocks authentication while preserving `UserId` and canonical-email ownership, so reinvitation cannot
  create a duplicate. Authorized restoration returns that same identity to its appropriate non-deleted state
  and records actor and reason.
- Implement pending-invitation correction as its own slice. Atomically replace the pending address, revoke the
  predecessor activation grant and pending delivery, and issue an unrelated fresh grant and delivery without
  mutating or reusing the old credential.
- Give Role inspection and Permission inspection separate read-only vertical slices. Administrators may view
  paginated Managed and custom records. Role views expose exact Permission membership and mark Managed Roles
  as version-controlled and runtime-immutable. Permission views expose administrative tier and explain
  `ADMIN_SAFE` versus `SUPER_ADMIN_ONLY` without implying Role hierarchy or magic `ROLE_` semantics.
- Keep explicit Permission creation and removal Application commands for reconciliation, seeding, testing,
  and project-owned CLI workflows, but expose no generic Permission mutation UI in the React starter. A
  developer CLI may help add and exercise a new Permission; managed definitions and authorization-check
  parity remain version-controlled, and removal fails while code or assignments still reference it.
- Keep custom Role administration in the React starter. Projects may create domain-specific Roles and compose
  them from existing Permissions, while Managed Roles remain version-controlled and runtime-read-only.
- Prove private realtime through two authenticated administrator browsers on the paginated user screen. A
  project-owned subscriber translates selected committed domain events, such as the eventual event for user
  deletion or removal, into an authorized socket-topic invalidation. The second client does not mutate its
  cached list from the event payload; it refetches the current page through the authoritative users API.
- Maintain an explicit event-to-page/topic subscription matrix. Include page-level invalidation and
  current-user topics in the starter; retain an extensible pattern for job-owner topics without inventing a
  Job model in this skeleton. Documentation may use long-running uploads as an example of later lifecycle
  notifications, including a safe link to inspect correctable failures. Socket updates remain minimal and do
  not expose raw domain-event payloads by default.
- Map each explicitly publishable domain event through its own Application transformer, such as a future
  `UserRegisteredTransformer` colocated with the AccessControl User socket concerns. Each transformer owns
  one stable public event name, schema version, allowlisted payload, allowed topic families, and allowlisted
  metadata; no central transformer contains a switch over all domain events. Framework composition collects
  the transformers using its accepted native registration style, with explicit definitions in Slim.
- Publish a versioned public realtime envelope rather than `Message::toArray()`. The envelope carries message
  identity, stable public event name, schema version, occurrence time, authorized topic, safe public payload,
  and allowlisted metadata such as correlation identity. PHP FQCNs, arbitrary metadata, and non-public domain
  fields never become browser contracts implicitly.
- Generate TypeScript HTTP view types such as `UserView` from OpenAPI and generate discriminated realtime
  envelope unions from versioned JSON Schemas. The build rejects drift between authoritative public schemas
  and committed generated client types.
- Define reusable authorized topic families for page or resource invalidation, current-user lifecycle, and
  owner-scoped long-running operations. The skeleton implements the first two and documents the operation or
  job pattern without inventing a Job aggregate.
- Prove invocation-mode portability with pending security-email delivery: the same Application handler and
  subscriber work under focused synchronous tests and the project's asynchronous event worker without
  Application branching.
- Keep Managed Role/Permission reconciliation as a documented framework-native console operation with
  deterministic dry-run/apply parity, complete preflight, and one atomic apply. It has no React screen.
- Supply the first login slice with an explicit development/test seed fixture containing an active user.
  The fixture is bootstrap data only and is not a production account-creation path. Later invitation and
  activation slices prove the real user lifecycle without depending on fixture creation at runtime.
- Use the relational database as the authoritative refresh-session store in all five framework projects.
  Login therefore commits its session and required audit record in one database transaction. Redis may be
  evaluated later as an optional fail-closed read projection or cache, but it is not required by the
  framework-portability proof and does not become authoritative session storage in these prototypes.
- Prove the login slice with both an HTTP functional test and a browser-level test. The HTTP test covers the
  JSend response, refresh cookie, and failure behavior; the browser test covers form submission, redirect,
  and the authenticated home-page indication. After automation is green, provide fake local test credentials
  and a bounded review card so John can accept the journey in his local browser. Never commit or display a
  production credential.
- Adopt user acceptance testing as a required pre-completion gate for each user-facing vertical slice in
  these starter projects. After automated checks pass, provide fake fixtures, the exact local URL, expected
  results, and any cleanup step for John's bounded local-browser review. Backend-only work does not require
  browser UAT. This is a starter-project workflow decision, not a universal rule imposed on every consumer.
- Atomically record successful login and session creation. Failed login attempts remain subject to
  throttling and security monitoring, but individual durable failure-audit writes are capped both per source
  IP address and globally within configurable time windows so a local or distributed attack cannot amplify
  the audit table without bound. Reaching an audit-write cap does not disable authentication throttling,
  generic responses, counters, or operational alerts.
- After an individual failed-login audit cap is reached, retain bounded time-bucket counters for total
  attempts, suppressed individual records, and distinct sources where practical. Those aggregates preserve
  distributed-attack evidence without accepting an attacker-controlled number of durable rows.
- Derive the per-IP cap from the direct connection address unless the request arrived through an explicitly
  trusted proxy. Only trusted-proxy configuration authorizes interpretation of the selected forwarded-address
  header; public clients cannot choose their own audit or throttling identity through `X-Forwarded-For`.
- Carry each vertical slice through all five framework projects before beginning the next shared use case.
  No framework project becomes the accidental design authority by racing ahead through the capability set.
- Keep one canonical use-case contract with the shared AccessControl work. Each framework repository owns
  an implementation ticket that links to that contract and adds only its framework-specific acceptance and
  composition details; it does not copy and independently redefine the use case.
- When one framework cannot implement a shared slice naturally, keep the passing projects green, record the
  failing executable case, and pause advancement to the next shared slice while the common seam is resolved.
  Do not add speculative framework branches to portable Application code or discard passing evidence.
- Each starter owns migrations in its native migration system. PostgreSQL and MySQL or MariaDB schema
  receipts prove equivalent identities, uniqueness constraints, and behavior; portable source text is
  not required.
- Select Yii Active Record over raw Yii DB commands and CodeIgniter Model over a Query Builder-only repository.
  Keep both frameworks' record and Model classes in Adapter code and map them explicitly to unchanged portable
  aggregates. Yii uses explicit join records for exact relationship replacement. CodeIgniter Models remain
  table-focused and may use their available Query Builder internally while the repository owns aggregate and
  relationship composition. The lower-level alternatives remain valid escape hatches, not starter defaults.
- Let each framework own the flavor of command, query, and event handler registration while proving one
  complete, inspectable resolved map with build failures for missing, duplicate, or ambiguous handlers. Slim
  uses explicit PHP-DI definitions and performs no per-request classpath scanning. Laravel and Symfony may
  use native discovery or autoconfiguration when their prototypes prove the resulting routes remain visible
  and deterministic; Yii and CodeIgniter likewise use the smallest idiomatic project-owned composition.
- Treat `TransactionalUnitOfWork::commitTransactional()` as the portable center. The callback contains the complete
  mutation and its required audit write. New portable Application code does not depend on `commit()`.
- Preserve `TransactionalUnitOfWork::isClosed()` with one portable meaning: it reports that the unit of work is
  terminally unable to accept another operation. Transaction rollback alone does not imply closure.
- Do not support nested `commitTransactional()` calls. A nested call fails explicitly rather than
  depending on framework-specific savepoint or nesting behavior.
- Deprecate `commit()` only if executable evidence shows that at least one supported native adapter cannot
  implement its documented pending-change semantics without buffering writes or inventing an identity
  map. Preserve Doctrine behavior through 1.x and reserve removal for 2.0.
- In every starter prototype, the business mutation and required audit record use the same relational
  database and one transaction. Both commit or both roll back. Split-store audit handoff and outbox design
  are outside WF-017 and require a real consumer need before they are designed.
- Event-sourced persistence is a separate case: committed events are authoritative history, while
  projections and post-commit event publication consume that history later using their own checkpoints or
  cursors. A projected audit view does not replace the required authoritative audit evidence.
- The required AccessControl walking slice uses conventional aggregate repositories. Event Sourcing remains
  an optional consumer architecture and is not part of the five-project framework-portability proof. Any later
  AccessControl Event Store prototype is an independent effort.
- For Symfony, Yii, CodeIgniter, and Slim, target Mercure 1.0 authorization with compatibility mode disabled.
  Pin the official `dunglas/mercure:v1.0.0-alpha.3` public preview only in disposable WF-017 prototypes; it
  proves the OAuth 2 `authorization_details` flow but is explicitly not production-ready. Do not implement the
  legacy `mercure` JWT claim or enable compatibility mode without a real supported consumer requirement.
  Replace the alpha with a stable 1.0 hub and rerun the receipt before publishing a supported starter. The
  compatible PHP integration version remains a prototype finding. Laravel retains its accepted native Reverb
  private-channel composition.
- For Symfony, Yii, CodeIgniter, and Slim, reverse-proxy the Mercure hub under the application origin and mint
  a short-lived exact-topic subscription credential only after revalidating the authoritative principal. The
  starter action sets the Mercure 1.0 `__Secure-mercure_access_token` cookie as `Secure`, `HttpOnly`,
  `SameSite=Strict`, and scoped to `/.well-known/mercure`; no cross-origin cookie or CORS composition is needed
  for the default. Use 60 seconds as the prototype credential lifetime pending browser reconnect evidence.
- Keep Laravel on its native `/broadcasting/auth` Reverb/Pusher private-channel response. Both realtime
  compositions deny the next authorization or renewal immediately after authoritative session revocation.
  Neither endpoint retroactively terminates an already accepted credential or open socket, so server-side
  disconnect or a bounded reconnect policy remains an explicit later prototype question.
- Preserve Fight Common's existing two-argument `Publisher` contract for public updates and add a separate
  framework-neutral `PrivatePublisher::pushPrivate(topic, message)` port for authorized realtime publication.
  The current `MercureHubPublisher` always constructs a public update, and adding a privacy argument to its
  existing interface would break third-party implementors. A Mercure private adapter sets the native private
  flag; Laravel owns a project adapter over its native Pusher/Reverb broadcaster.
- Keep public realtime transformation in the consuming AccessControl Application layer. One transformer per
  explicitly publishable domain event owns its stable public event name and schema, accepts only its declared
  topic family, and emits the versioned public envelope. Framework composition supplies the authorized
  transport address. A users-page invalidation carries no User identity or domain-event fields and allowlists
  only correlation identity from message metadata.
- Default project composition to `SynchronousCommandBus` for commands and `AsynchronousEventDispatcher` for
  domain events. A controller that deliberately accepts work for later processing may request
  `AsynchronousCommandBus` and return HTTP 202; a use case that deliberately needs immediate event fan-out
  may request `SynchronousEventDispatcher`. These are explicit composition choices, not invocation-mode
  branches inside AccessControl Application handlers.
- Keep each framework's authenticated identity as a thin request-scoped credential reference. A starter-owned
  `CurrentPrincipalProvider` revalidates current account state, authentication version, and session ownership
  or revocation against authoritative storage once per request, then returns the immutable portable
  `AuthenticatedPrincipal` snapshot. Shared aggregates implement no framework security interface.
- Select native principal entry points without adding a shared adapter: Symfony token storage, Laravel's
  request guard, Yii authentication middleware's request identity, CodeIgniter's authentication-implementation
  user-ID convention behind a filter/service adapter, and a PSR-15 request attribute in Slim. Missing,
  disabled, stale-version, revoked-session, and wrong-session-owner identities all fail closed.
- Keep authorization outcomes framework-neutral and let each starter map them through its native HTTP response
  type. The users-list action returns the same JSend `success`/`fail` body and HTTP 200/401/403 semantics in all
  five projects; this does not require a shared HTTP-response contract.
- Retain Fight Common's existing Symfony-backed `JSendResponse` as an optional Symfony convenience. Do not make
  it an Application result or require Laravel, Yii, CodeIgniter, or Slim to translate through a Symfony type.

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

## Bounded prototype evidence: transaction center

The retained `prototype/wf-017-transaction-seam` branch answers one question only: can the unchanged
`UnitOfWork::commitTransactional()` callback contain a session mutation and its required audit write across
all five selected framework compositions? The runnable source, isolated dependency locks, one-command runner,
five framework receipts, and one guarded Doctrine comparison receipt live under
`planning/wayfinder/prototypes/wf-017-transaction-seam/` on that branch.

The SQLite evidence passes callback-result preservation, atomic commit, forced rollback, and exception
propagation for Symfony and Slim through Doctrine ORM 3.6/XML mapping, Laravel through its native database
transaction, Yii through Yii DB, and CodeIgniter through a manual native transaction with explicit status
checks. This is sufficient evidence that the callback method is the portable transaction center; it does not
require a change to the transaction callback signature.

The experiment also records two narrower findings:

- record-oriented Laravel, Yii, and CodeIgniter adapters have no natural pending-change meaning for
  `commit()` because their repository writes execute immediately; implementing the documented semantics
  would require buffering or an invented identity map. This triggers the accepted 1.x deprecation boundary
  and justifies one additive `TransactionalUnitOfWork` port declaring `commitTransactional()` and
  `isClosed()`. The legacy `UnitOfWork` extends that narrower port and retains deprecated `commit()` plus
  Doctrine's current behavior through 1.x; record-oriented adapters implement only the narrower port. Remove
  `commit()` in 2.0 rather than forcing a no-op or unsupported method into those adapters;
- the current `DoctrineUnitOfWork` permits a nested `commitTransactional()` call, while the three disposable
  native adapters reject nesting explicitly. The Doctrine lane therefore fails the accepted nesting policy.
  Resolve that behavior through the smallest Fight Common implementation ticket before advancing to the next
  shared walking slice; the nesting correction requires no transaction-port signature change.

A follow-up comparison receipt proves the smallest Doctrine correction shape: a readonly adapter can inspect
DBAL's active transaction nesting level before delegating to `EntityManagerInterface::wrapInTransaction()`.
The disposable guarded adapter preserves callback results, atomic commit, rollback, exception propagation,
and Doctrine close-on-rollback behavior while rejecting the nested call with `LogicException`. The production
adapter remains unchanged on this prototype branch; carry this adapter-local guard and its focused regression
test into the Fight Common implementation ticket. No mutable guard state, wrapper abstraction, or change to
the `TransactionalUnitOfWork` operation signatures is justified. The separate additive port split above is
required by `commit()` portability, not by Doctrine's nesting behavior.

The receipts use SQLite for fast deterministic proof. PostgreSQL and MySQL/MariaDB parity, aggregate and
relationship hydration, concurrency, HTTP, principal integration, realtime authorization, the React client,
and the remaining AccessControl behaviors are still open WF-017 prototype lanes.

## Bounded prototype evidence: additive transaction contract split

The retained `prototype/wf-017-transactional-uow-split` branch answers the follow-up compatibility question:
can the additive 1.x `TransactionalUnitOfWork` split preserve legacy Doctrine consumers while allowing native
record adapters to omit `commit()` entirely? Its one-command runner, candidate contracts, real framework
adapters, and five machine-readable receipts live under
`planning/wayfinder/prototypes/wf-017-transactional-uow-split/` on that branch.

The comparison passes in all five starter compositions. One portable Application function type-hints only
`TransactionalUnitOfWork`, preserves the callback result, and atomically commits the session mutation and
audit write through Doctrine ORM 3.6, Illuminate Database 13, Yii DB 2, and CodeIgniter Database 4. Doctrine's
candidate adapter also implements legacy `UnitOfWork`; an unchanged legacy consumer successfully calls
`commit()` to flush pending state. The Laravel, Yii, and CodeIgniter candidates implement only
`TransactionalUnitOfWork`, are not instances of legacy `UnitOfWork`, and expose no `commit()` method.

This confirms a source-compatible additive 1.x contract change, not an unchanged contract: add
`TransactionalUnitOfWork` with `commitTransactional()` and `isClosed()`, then make legacy
`UnitOfWork extends TransactionalUnitOfWork` while retaining deprecated `commit()` and Doctrine behavior
through 1.x. Existing
`UnitOfWork` consumers keep their method surface; new portable consumers and record adapters depend on the
narrower port. Remove `commit()` with the legacy contract in 2.0. Framework container alias compatibility is
an implementation concern still requiring focused production tests; the prototype changes no production
source.

## Bounded prototype evidence: record-to-aggregate mapping

The retained `prototype/wf-017-record-mapping` branch answers the next persistence question: can all five
starter compositions round-trip one unchanged aggregate and exact Role membership without leaking framework
records, and which native record styles should Yii and CodeIgniter select? Its common aggregate/repository
probe, seven candidate lanes, pinned dependency lock, and machine-readable receipts live under
`planning/wayfinder/prototypes/wf-017-record-mapping/` on that branch.

All seven lanes pass create, rehydrate, update, identity preservation, and exact relationship replacement.
Symfony and Slim use adapter-owned Doctrine XML records; Laravel uses adapter-owned Eloquent records and
`belongsToMany()->sync()`. The Yii comparison proves both Yii Active Record 1.1 and Yii DB 2 can preserve the
boundary, but selects Active Record because its stable native record package supplies identity and row-state
mechanics without making the aggregate an Active Record. The join remains an explicit Adapter record.

The CodeIgniter comparison proves both Model and Query Builder repositories. Select CodeIgniter Model as the
starter default because CodeIgniter documents it as the ordinary table gateway and exposes Query Builder
through it. Models return arrays rather than domain objects; the repository maps those rows and owns the
multi-table aggregate composition. The Model lane requires the normal minimal application bootstrap that a
starter already has, while the lower-level Query Builder candidate remains available for exceptional queries.

This evidence selects persistence record styles only. On that branch, PostgreSQL/MySQL or MariaDB migration
parity and concurrent canonical-email uniqueness remained open; the following bounded evidence closes the
schema-behavior part of that question. HTTP, principal integration, handler registration, realtime, and client
behavior remain open. The record-mapping prototype does not re-prove the preceding transaction evidence. No
production source changes.

## Bounded prototype evidence: migration and canonical-email uniqueness

The retained `prototype/wf-017-migration-uniqueness` branch answers the next schema question: can all five
starter compositions create equivalent PostgreSQL and MySQL schema behavior that rejects concurrent claims
for one canonical email across account states while every relationship remains keyed by `UserId`? Its native
schema candidates, disposable database runner, two-connection race, and ten machine-readable receipts live
under `planning/wayfinder/prototypes/wf-017-migration-uniqueness/` on that branch.

All ten framework/database lanes pass against MySQL 8.4.11 and PostgreSQL 17.10. Symfony and Slim use
Doctrine DBAL schema operations intended for their Doctrine Migrations compositions; Laravel uses Schema
Builder; Yii uses Yii DB's driver-specific DDL commands; CodeIgniter uses Forge. The CodeIgniter lane also
proves its starter runtime must load the native `mysqli` and `pgsql` extensions in addition to Fight Common's
PDO-only development image.

In every lane, one `PENDING_ACTIVATION` transaction writes `same@example.test` and pauses before commit while
a second connection attempts a `DELETED` user with the same canonical email. After the first transaction
commits, the second loses to the database unique constraint with SQLSTATE `23000` on MySQL and `23505` on
PostgreSQL. A different canonical email succeeds in another state. The discoverable named index is isolated
as `uniq_users_canonical_email (canonical_email)`, so its documented tenant evolution is replacement by
`(tenant_id, canonical_email)` without changing `UserId`. The `user_roles` foreign keys and receipt data prove
relationships continue to use `user_id` and `role_id`.

This closes pinned PostgreSQL/MySQL DDL and concurrency behavior, not complete migration lifecycle wiring.
The prototype invokes each selected schema API directly and does not prove migration discovery, history-table
management, rollback, or deployment ordering through full framework applications. It uses the minimum User,
Role, and assignment schema rather than the full AccessControl model, and it does not select application
retry, idempotency, or HTTP conflict mapping. On that branch, handler registration remained open; the following
bounded evidence closes its composition question. HTTP, principal integration, realtime, client behavior, and
the complete walking slice remain open. No production source changes.

## Bounded prototype evidence: handler composition

The retained `prototype/wf-017-handler-composition` branch answers the next composition question: can all five
starter compositions build one complete, inspectable command/query/event map for unchanged portable
Application handlers and reject missing, ambiguous, or duplicate registrations during boot? Its shared
prototype handlers, five native container compositions, failure probes, one-command runner, pinned dependency
lock, and machine-readable receipts live under
`planning/wayfinder/prototypes/wf-017-handler-composition/` on that branch.

All five lanes pass with Symfony DependencyInjection 8.1.4, Illuminate Container 13.25.0, Yii DI 1.4.1,
CodeIgniter 4.7.4 Services, and PHP-DI 7.1.1 for Slim. Every valid composition resolves the same one-command,
one-query, and one-event-subscription map and dispatches through the resolved services. Every lane also fails
before serving work when the required command handler is absent, two command handlers claim the same message,
or the same event-subscriber class is registered twice. Multiple distinct subscribers for one event remain
valid fan-out rather than ambiguity.

Select the following starter compositions:

- Symfony uses compile-time interface autoconfiguration and native service tags. The starter's container
  build compiles those tags into the inspectable Fight handler map; there is no request-time scan.
- Laravel registers handlers in a project service provider and groups them with native container tags.
- Yii declares tagged services in the normal `config/common/di` configuration.
- CodeIgniter exposes one explicit project-owned `Config\\Services` handler-catalog factory. Do not depend on
  Services auto-discovery ordering for uniqueness because CodeIgniter documents that the first duplicate
  service method found wins.
- Slim uses explicit PHP-DI definitions and handler-ID lists with autowiring disabled for this map. It performs
  no classpath scanning.

Each project owns its native service collection, then applies the same boot-time conformance rule: exactly one
handler for every required command and query, no unregistered required message, and no duplicate subscriber
class registration. The resulting map remains inspectable in tests and diagnostics. This is a project
composition contract and conformance-test concern; the evidence does not justify a new shared runtime
container or framework branch inside portable Application handlers.

This closes the bounded handler-registration composition question, not full framework-kernel cache wiring or
the complete AccessControl map. HTTP, realtime authorization, client behavior, and the complete walking slice
remain open. No production source changes.

## Bounded prototype evidence: principal integration

The retained evidence under `planning/wayfinder/prototypes/wf-017-principal-integration/` answers the next
security-boundary question: can all five starter compositions translate their native authenticated request
identity into the same portable principal while authoritative account and session state remain the source of
truth? Its locked isolated dependencies, native-boundary candidates, fail-closed scenario matrix, runner, and
five machine-readable receipts are committed with this ticket.

All five lanes pass. Symfony uses Security Core token storage and a starter-owned `UserInterface` wrapper;
Laravel uses an Illuminate `RequestGuard`; Yii reads the `IdentityInterface` request attribute established by
its authentication middleware; CodeIgniter uses its documented authentication-implementation/user-ID
convention behind a project filter/service; Slim uses a PSR-7 request attribute populated by project PSR-15
middleware. None makes the shared User aggregate implement a framework interface.

In every lane, the native identity carries only the request credential reference: `UserId`, session identity,
and authentication-version claim. The unchanged provider reloads authoritative state and returns an immutable
`AuthenticatedPrincipal` only for the active, current, owned, non-revoked session. Anonymous, missing-user,
disabled-user, stale-version, revoked-session, and wrong-session-owner scenarios all return no principal.

This selects starter-owned provider adapters and requires no Fight Common adapter or shared-contract change.
The in-memory lookup is bounded seam evidence, not a complete authentication flow: JWT validation, credential
login, refresh/cookie behavior, CSRF/CORS, native kernel/filter ordering, authorization policy, HTTP responses,
realtime credentials, and browser behavior remain open. No production source changes.

## Bounded prototype evidence: native HTTP action

The retained evidence under `planning/wayfinder/prototypes/wf-017-http-action/` answers the next delivery
question: can one unchanged authorized users-list query produce consistent JSend semantics through every
selected framework's native action and response type without turning Fight Common's Symfony-backed
`JSendResponse` into a cross-framework contract? Its locked isolated dependencies, shared handler/outcome,
five native response candidates, one-command runner, and machine-readable receipts are committed with this
ticket.

All five lanes pass the same three scenarios. A principal with `LIST_USERS` receives HTTP 200 and JSend
`success` with the users view. An anonymous request receives HTTP 401 and JSend `fail` with
`authentication_required`. An authenticated principal without the permission receives HTTP 403 and JSend
`fail` with `forbidden`. Every response declares JSON and no native request or response type enters the
portable handler or outcome.

Symfony maps to `JsonResponse`; Laravel maps to its native `JsonResponse`; Yii returns the application's
selected PSR-7 response; CodeIgniter maps through its native `Response` message API; Slim writes and returns a
PSR-7 response. Each starter owns that thin delivery mapping. Fight Common's current `JSendResponse` remains
useful to Symfony consumers but does not become the portable result or a dependency of the other projects.
This evidence therefore requires no Fight Common adapter or contract change.

This is action/response seam evidence, not five complete application kernels. Native route declarations are
recorded, but route discovery, middleware/filter ordering, container compilation, pagination input,
validation/not-found/conflict mapping, OpenAPI generation, CORS/CSRF, cookies, login, and exception-subscriber
behavior remain outside this bounded question. Framework-native realtime integration, client behavior, and
the complete end-to-end walking slice remain open. No production source changes.

## Bounded prototype evidence: Mercure protocol version

The retained evidence under `planning/wayfinder/prototypes/wf-017-mercure-version/` answers the version
question that precedes four framework integrations: can the accepted Mercure 1.0 OAuth 2 private
publish/subscription flow run with compatibility mode disabled, and what boundary follows from the current
stable and preview images? Its one-command runner starts each official image on an ephemeral loopback port,
uses short-lived exact-topic credentials, writes machine-readable receipts, and removes the containers.

Both lanes pass their expected fail-closed comparison. Stable `dunglas/mercure:v0.24.2` accepts the legacy
`mercure.publish` / `mercure.subscribe` claim and `topic` subscription parameter, while rejecting the 1.0
OAuth 2 access token and withholding the private update from its `match` subscription. The official
`dunglas/mercure:v1.0.0-alpha.3` public preview does the inverse with compatibility mode disabled: its
`authorization_details` token publishes and receives the private update through `match`, while the legacy
credential and query shape fail closed.

Select `v1.0.0-alpha.3` only as disposable evidence for the next WF-017 Mercure prototypes. Upstream labels it
a testing prerelease that must not run in production and may change before final 1.0.0. Do not enable
compatibility mode or fall back to the rejected legacy protocol to make current PHP integrations pass. Before
a starter can be presented as supported, pin a stable 1.0 hub and rerun this receipt. This finding does not
change Fight Common's `Publisher`, does not modify `MercureHubPublisher`, and does not affect Laravel's Reverb
selection.

This closes the hub protocol/version question, not framework-native publishing adapters, subscriber-token
cookie responses, trusted origin and cookie attributes, CORS, reconnect/recovery, two-browser invalidation,
public envelope schemas, Laravel Reverb, or client refetch behavior. No production source changes.

## Bounded prototype evidence: realtime subscription authorization

The retained `prototype/wf-017-realtime-authorization` branch answers the credential-boundary question that
follows the Mercure version probe: can all five starter compositions authorize the same `LIST_USERS` page
subscription from a freshly revalidated principal, deliver the credential through their selected native
boundary, reject unapproved topics, and deny the next authorization immediately after authoritative session
revocation? Its framework-neutral decision, native response candidates, locked dependencies, one-command
runner, and five machine-readable receipts live under
`planning/wayfinder/prototypes/wf-017-realtime-authorization/` on that branch.

All five lanes pass. Symfony, Yii, CodeIgniter, and Slim mint a Mercure 1.0 OAuth 2 access token containing one
exact `subscribe` match and return it through their selected native response type as the secure hub cookie.
The default same-origin reverse proxy keeps that credential out of response bodies and avoids a cross-origin
cookie/CORS dependency. Laravel uses Illuminate's native `PusherBroadcaster` authorization path for the
selected Reverb private channel; the receipt independently verifies the returned Pusher-protocol signature.
Framework request and response types remain outside the shared authorization decision.

Anonymous requests and unapproved topics receive no credential. After the session is revoked in authoritative
storage, every lane denies the next renewal or private-channel authorization. The already-issued Mercure token
still verifies for the remainder of its bounded 60-second lifetime, and a Reverb socket already authorized is
not disconnected by a later HTTP denial. This selects credential delivery but deliberately leaves open the
browser reconnect/recovery and server-side disconnect policy needed to characterize or shorten the residual
open-connection window.

This evidence does not start Reverb, publish an event, prove post-commit delivery, define the public realtime
envelope, generate client types, or exercise two-browser invalidation and authoritative refetch. Those remain
separate WF-017 lanes. No Fight Common or Fight AccessControl contract change is justified, and no production
source changes.

## Bounded prototype evidence: private realtime publication

The retained evidence under `planning/wayfinder/prototypes/wf-017-realtime-publication/` answers the next
publication question: can one committed `UserDeleted` event become the same minimal, versioned users-page
invalidation in all five starter compositions and publish privately through Mercure or Laravel Reverb without
serializing PHP classes, arbitrary metadata, or domain state? Its isolated dependency lock, shared transformer
and subscriber, native transport candidates, one-command runner, and five machine-readable receipts are
committed with this ticket.

All five lanes pass. Symfony, Yii, CodeIgniter, and Slim use Symfony Mercure 0.7.2 against an in-process native
hub double. The runner first exercises Fight Common's live `MercureHubPublisher` and proves that its existing
`push(topic, message)` path creates a public update. The additive candidate then publishes the same envelope
to the exact authorized topic with Mercure's private flag set. Laravel 13.25.0 delegates through its native
`PusherBroadcaster`; the receipt records and independently inspects the signed Pusher/Reverb event request,
private channel, stable event name, and envelope body.

The shared `UserDeletedTransformer` owns public event name `access.user.deleted`, schema version one, and the
`access.users.page` topic family. Its envelope preserves message identity and occurrence time, carries only an
authoritative-refetch invalidation, and allowlists `correlation_id`. The executable leak checks prove that the
domain User identity, private administrative reason, PHP event FQCN, causation identity, and trace metadata do
not reach either transport. An unapproved topic family fails before publication.

This evidence rejects a change to the existing `Publisher` signature and selects the smallest additive port:
`PrivatePublisher::pushPrivate(string $topic, string $message): void`. Existing public publishers and consumers
remain source-compatible. A future Fight Common implementation ticket may add the port and Mercure adapter;
the Laravel implementation remains starter-owned because Reverb is the selected native composition.

The runner invokes the subscriber only after a deterministic commit probe and shows that a later transport
failure cannot undo committed state. It does not prove an asynchronous worker, durable retry, outbox, running
hub or Reverb server, browser delivery, reconnect policy, two-browser invalidation/refetch, JSON Schema,
generated TypeScript unions, or schema-drift rejection. Those remain separate WF-017 lanes. No production
source changes.

## Resolution boundary

Produce bounded prototype evidence and decisions, not polished starter implementations. If a seam
fails, prefer the simplest framework implementation and revise the smallest shared abstraction. Do
not force mechanical parity with Doctrine or promote experimental prototypes as supported releases.
