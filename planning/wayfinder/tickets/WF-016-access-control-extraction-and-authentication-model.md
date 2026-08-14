# Specify the Fight AccessControl extraction and authentication model

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Audit Fight Common contracts and the 1.2 compatibility envelope](WF-014-fight-common-contract-and-compatibility-audit.md), [Define the portable AccessControl and persistence boundaries](WF-012-access-control-and-persistence-boundaries.md)
**Research:** [WF-016 research note](../research/WF-016-access-control-extraction-and-authentication-model-research.md)
**Decisions:** [ADR 0022 — Invited Registration and Multi-Session JWT Authentication](../../adr/0022-invited-registration-and-multi-session-jwt-authentication.md)

## Question

What exact Domain, Application, session, authentication, authorization, and reference-data contracts
belong in the first public Fight AccessControl package?

## Must decide

- source comparison across `project`, Fight CMS, and Omphalos, selecting the strongest current
  behavior without copying project-specific features;
- removal of Symfony Security and token-storage dependencies from shared layers;
- whether internal Doctrine Collections remain and the exact framework-neutral public collection
  surface;
- aggregate, command, query, event, repository, read-model, password, account-state, authorization,
  and authenticated-principal contracts in `0.1.0`;
- reusable Managed Role/Permission reconciliation inputs and results, stable UUID ownership, collision rules,
  dry-run, custom-record preservation, and UnitOfWork behavior;
- supported framework-native cookie-session alternative and its integration boundary;
- required SPA profile: short-lived in-memory access JWT, opaque
  HttpOnly refresh credential, hashing at rest, token-family rotation, atomic reuse detection,
  revocation, CSRF and Origin defenses, logout scope, and password-change effects;
- frontend single-flight refresh, awaited request retry, clock skew, bootstrap identity, multiple tabs,
  and authorization-display versus server-authority rules; and
- private realtime publication and subscription authorization, including purpose-specific short-lived
  credentials, exact topic rights, session revocation effects, and the boundary between AccessControl and
  provider-specific Mercure or framework-native channel adapters; and
- which security-sensitive behavior requires dedicated threat modeling and primary-source review.

## Decisions

- Domain and Application remove Doctrine Collections and Symfony Security/token-storage dependencies.
  Fight AccessControl has no Adapter layer; consumer projects translate persistence and framework
  facilities at their outer boundaries.
- Registration is an invitation journey: an authorized actor creates a `PENDING_ACTIVATION` user, the
  package sends an activation email, a single-use activation grant sets the first password, and the user
  becomes `ACTIVE` with an authenticated session.
- The required starter profile is a short-lived in-memory access JWT plus an opaque HttpOnly refresh
  credential backed by a database or Redis session store shared across application container instances.
  Each consumer-owned implementation must provide atomic session mutation. Multiple browser and
  remote-device sessions coexist independently.
- A consumer may substitute an optional framework-native cookie session only if it passes the identical
  shared-store, multi-device, revocation, CSRF, session-management, and audit behavior suite; it is not a
  second AccessControl model or the certified `/client` default.
- Remember-me extends only the current refresh session and persists its cookie across browser restart.
  Current-session logout leaves other sessions active; logout-all, disablement, password change, and
  password reset revoke every session.
- Activation and reset email delivery are required package behavior. AccessControl owns invocation-neutral
  delivery event subscribers and use cases. Projects own their template-engine and mail-service adapters,
  sender, URL generation, sync/async routing, queues, and unrestricted templates. The documented template
  model contains only safe display values, the one-time action URL, and expiry—not aggregates, hashes,
  digests, or security context.
- Failed activation-email delivery leaves registration durably pending and visible to the inviter. A
  rate-limited resend revokes the predecessor activation grant and retries delivery without duplicating the
  user or claiming the first attempt succeeded.
- Canonical email is unique across pending, active, disabled, and soft-deleted users, with Application
  preflight plus a consumer persistence constraint. Pending reinvitation uses resend; active/disabled users
  are not duplicated; deleted users require restoration. Stable `UserId`—never email—owns relationships.
  The single-tenant uniqueness rule is isolated so a future consumer can replace it with
  `(tenant_id, canonical_email)` without changing unrelated identity references.
- Email change is a dedicated request/confirm journey. The old email remains authoritative while the new
  canonical address is reserved and receives a single-use confirmation grant. Confirmation advances
  authentication version and revokes all sessions; cancellation/expiry releases the reservation. A
  super-admin may initiate but cannot bypass new-address confirmation.
- `EmailChangeGrant` is a distinct purpose-bound aggregate. User initiation requires the current password;
  super-admin initiation requires a dedicated permission, atomic audit, and reason. Both paths notify the
  old mailbox. One active grant per user and one reservation per target address are allowed; existing users
  outrank reservations. Replacement revokes the predecessor, self-cancel and audited admin cancel are
  supported, requests are throttled, and the configurable starter expiry is 24 hours.
- Invitation/reset commands atomically commit the grant digest and a pending delivery record containing a
  short-lived authenticated-encrypted credential under a key outside the database. The post-commit event
  identifies that delivery; the subscriber decrypts only in memory, renders/sends, and destroys the
  ciphertext after success or terminal expiry. Pending work remains discoverable if event dispatch fails.
- Password-reset requests use the same secret-free subscriber boundary. Public requests always
  return accepted; account eligibility and delivery outcomes remain internal and non-sensitive.
- A `DISABLED` user may reset a password but remains disabled, receives no session, and cannot authenticate
  until explicitly enabled. Deleted users receive no reset grant; pending users use activation.
- Only `ACTIVE` users may request or confirm email change. Disablement cancels its pending grant and
  reservation; pending users use invitation correction and deleted users require restoration first.
- `CorrectPendingInvitationEmail` is audited and super-admin-only. It atomically changes the pending
  canonical email, revokes the old activation grant and delivery, releases the old address, and creates a
  fresh activation grant and delivery without activating the user or reusing a credential.
- Managed Roles have stable UUIDs and exact declared permission membership. Reconciliation removes
  undeclared assignments from Managed Roles and leaves consumer-created Roles untouched. Every Managed
  Permission is seeded to `ROLE_SUPER_ADMIN`; only permissions explicitly classified as admin-safe are
  also seeded to `ROLE_ADMIN`. Neither role bypasses named permission checks. `ROLE_ADMIN` has read-only
  access to users, roles, and permissions; all mutation of those records and sensitive integration controls
  remain super-admin-only.
- Permission administrative tier is version-controlled Managed Permission definition metadata, not a
  database column. Every Managed Permission declares `ADMIN_SAFE` or `SUPER_ADMIN_ONLY`; reconciliation
  derives Managed Role membership, tickets and pull requests state the tier, and automated validation
  rejects omissions.
- Managed Roles and Managed Permissions are read-only at runtime and change only through definition review
  and reconciliation. Super administrators may create and manage custom Roles and Permissions, which
  reconciliation preserves.
- Managed Permission definitions must exactly match named permission checks in the consumer codebase.
  Removing a Managed Permission requires automated proof that no code path still checks it; reconciliation
  then removes its assignments and live record. Audit evidence stores the historical permission name without
  requiring the live record to survive.
- Project static analysis compares typed Managed Permission references and definitions in both directions;
  generic enumeration and reconciliation do not count as authorization usage.
- Reconciliation performs complete collision/reference/parity preflight and applies one UnitOfWork. Each
  Managed Role uses Fight Common `HashSet` differences to derive `permissionsToAdd` and
  `permissionsToRemove`. Dry-run returns the same deterministic ordered plan and issues without mutation,
  publication, audit, or commit; approved Managed Permission removals are explicit actions.
- Deleting a custom Role or Permission is rejected while it remains assigned or referenced. Deletion never
  cascades; a super administrator must explicitly detach or reassign every dependency first.
- Role and Permission names are opaque portable values. `ROLE_SUPER_ADMIN` and `ROLE_ADMIN` retain their
  spelling, but AccessControl neither parses `ROLE_` nor infers hierarchy or authority from it. Managed
  Permission names use uppercase underscore identifiers, and custom names use the same validation.
- Activation and reset credentials are separate `ActivationGrant` and `PasswordResetGrant` contracts;
  they may share internal cryptography but cannot be used for each other's state transition.
- Starter grant expiries are 72 hours for activation, one hour for password reset, and 24 hours for email
  change. Values are configurable; each purpose has one current digest-backed, atomically single-use grant,
  and resend/rerequest revokes the predecessor.
- Successful state commits remain durable when subsequent event publication fails; the latter is reported
  as an infrastructure failure after durable success.
- Required security auditing is stricter: classified sensitive commands do not succeed unless a typed,
  secret-free audit record is committed atomically with the mutation. If consumer-owned stores cannot share
  that transaction, the project atomically writes a durable audit handoff/outbox. Consumers own persistence
  and optional request enrichment; ordinary Domain-event publication remains commit-then-publish.
- Secure realtime subscription rights derive from the active authoritative principal and do not reuse
  client display state or a broad ordinary access token as topic authority.
- Laravel uses its first-party Reverb broadcasting and private-channel authorization by default. Symfony,
  Yii, CodeIgniter, and Slim use a secured Mercure Hub container by default. Each starter prefers its
  framework's idiomatic integration while proving the same portable behavior.
- Refresh credentials rotate atomically once per successful use. A simultaneous losing request inside a
  short configurable conflict window receives only `refresh_conflict`, waits for the winning browser
  context, and retries once; reuse outside that window revokes only that device session family.
- Realtime application updates are private by default and anonymous subscriptions are disabled. Public
  updates require explicit classification that the underlying information is already unauthenticated.
  User UUIDs and topic names are identifiers, not secrets.
- Fight Common 2.0 replaces `Publisher::push(topic, message)` with an explicit public/private realtime
  update contract plus a subscription-authorizer port; Reverb and Mercure adapters enforce the same
  portable behavior.
- Starter defaults are a 15-minute access JWT; ordinary sessions use a browser-session cookie with one-day
  idle and two-day absolute server expiry; remembered sessions use a persistent cookie with 15-day idle
  and 30-day absolute expiry. Rotation advances idle expiry only, and deployments configure tested values.
- Every authenticated request validates current account state, authentication version, and session
  revocation through the shared state boundary; a still-valid access JWT does not delay revocation.
- Password change and reset revoke all sessions, clear current browser credentials, and require fresh login
  rather than silently creating a replacement session.
- API access and realtime subscription credentials use separate purpose-specific validation and preferably
  separate asymmetric keys, with exact type/issuer/audience/algorithm/time/subject/token-ID/key-ID checks
  and bounded overlapping-key rotation.
- Every starter includes a minimal user Active Sessions journey to list coarse session metadata, revoke one
  other session, or revoke all other sessions. It also includes a super-admin session-management journey
  guarded by `MANAGE_USER_SESSIONS`; `ROLE_ADMIN` does not receive it, and every administrative revocation
  is audited.
- Realtime subscription credentials last at most five minutes. Session revocation immediately prevents
  renewal; adapters disconnect sooner where supported, while the portable guarantee documents the bounded
  residual connection window and keeps sensitive state behind an API refetch.
- Every full SPA load awaits the refresh endpoint before authenticated rendering. A valid session returns a
  new 15-minute JWT and authoritative principal; proactive refresh begins at ten minutes old. All requests
  await one shared refresh promise, and an eligible authorization failure allows one refresh plus one
  replayable retry with bounded clock-offset handling.
- Authentication mutations enforce exact Origin and credentialed-CORS policy. Framework-native CSRF
  protection covers refresh/logout, login requires a custom same-origin header, and SameSite/Fetch Metadata
  remain defense in depth.
- The pre-MFA password policy requires at least 15 Unicode characters, permits at least 64 plus spaces,
  paste, autofill, and password managers, rejects common/contextual/breached values, uses no composition or
  periodic-change rules, NFC-normalizes, prefers benchmarked Argon2id, and rehashes on successful login.
  MFA, recovery, and step-up authentication are deferred beyond `0.1.0` behind extensible seams.
- Login, activation resend, and password reset are throttled by normalized identity and source signal with
  generic comparable-time responses, retry-after outcomes, monitoring, and no attacker-triggerable permanent
  account lockout. CAPTCHA/risk scoring remain replaceable integrations.
- `DISABLED` is reversible administrative suspension. `DELETE_USERS` performs a protected soft delete that
  revokes all sessions and grants and preserves a tombstone for audit/history. Only an audited super-admin
  `RESTORE_USERS` process may recover it. The super administrator chooses `ACTIVE` for a confirmed accidental
  deletion or `DISABLED` for reset/reactivation before enablement and supplies a reason. Both advance
  authentication version and restore no sessions or grants; active mode retains the password but requires
  fresh login. Consumer-owned retention/erasure handles personal data.
- The `0.1.0` aggregates are `User`, `Role`, `Permission`, `ActivationGrant`, `PasswordResetGrant`,
  `EmailChangeGrant`, and `RefreshSession`. JWTs, realtime credentials, email attempts, browser labels, and
  audit entries remain application results, delivery records, or infrastructure projections.
- Explicit commands cover invite/pending-email-correction/resend/activation,
  disable/enable/delete/restore, password change/reset,
  email-change request/confirm/cancel, role assignment, session revocation, and Managed Role/Permission
  reconciliation; there is no generic account-state update.
- Queries return immutable `UserView`, `RoleView`, `PermissionView`, `SessionView`, and pages. Aggregates
  remain behind command-side repositories, and no read model exposes credential or password material.
- Fight AccessControl contains only Domain and Application code. It depends on the compatible Fight Common
  line for neutral primitives and ports, duplicates none of them, and has no framework or Adapter layer. It
  owns typed `PlainPassword`, `PasswordHash`, and `PasswordVerification` boundaries; consumer-owned adapters
  compose Fight Common 1.x password services without breaking their signatures. Direct typed Fight Common
  signatures are deferred to its 2.0 compatibility work.
- Application handlers may create access-token material and `RefreshSession` aggregates through package
  ports and use cases. Command handlers and event subscribers are indifferent to synchronous or asynchronous
  invocation; each consumer project owns routing, queueing, retry, and worker policy. Test-only
  `InMemoryRepositories` may support package tests without becoming runtime adapters.

## Resolution boundary

This ticket is closed. Its decisions above form the package extraction specification and authentication
state model, supported by the linked research note and [ADR 0022](../../adr/0022-invited-registration-and-multi-session-jwt-authentication.md).
No proprietary business behavior was copied or derived. No source was moved and no package, adapter, or
repository was created. Prototype evidence now
proceeds through [WF-017](WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md).
