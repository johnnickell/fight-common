# ADR 0022: Invited Registration and Multi-Session JWT Authentication

- Status: accepted
- Date: 2026-08-13

## Decision

Fight AccessControl `0.1.0` uses an explicit invitation and activation lifecycle. An authorized actor
creates a user in `PENDING_ACTIVATION` without a password, the package sends an activation email containing
a one-time activation credential, and successful initial-password completion moves the user to `ACTIVE`
and establishes an authenticated session. Public self-registration is a separate future use case rather
than an alternate meaning of registration.

The required starter authentication journey uses a short-lived access JWT held only in client memory and
an opaque HttpOnly refresh credential backed by a shared server-side session store. A user may have
multiple simultaneous refresh sessions across browsers and remote devices. Login does not revoke unrelated
sessions; ordinary logout revokes only the current session. Logout-all, account disablement, password
change, and password reset revoke every session.

`remember me` is trusted server-side refresh-session policy. It gives only the current session a persistent
browser cookie and a longer configurable lifetime. It does not lengthen the access JWT, add authority, or
encode trusted policy in client-controlled cookie data.

The starter defaults are a 15-minute access JWT; an ordinary browser-session cookie with a one-day idle
and two-day absolute server timeout; and a remembered persistent cookie with a 15-day idle and 30-day
absolute timeout. Rotation may advance an idle deadline but never an absolute deadline. Deployments may
tighten or deliberately revise these values through tested configuration; they are not Domain constants.

Every authenticated request validates authoritative account and refresh-session state, including `ACTIVE`,
authentication version, session identity, and revocation. A valid JWT does not preserve a 15-minute
revocation window. A shared Redis projection may make these reads inexpensive, but it must fail closed and
remain consistent with the authoritative session mutation boundary.

Password change and password reset commit the new password, revoke every refresh session, invalidate the
authentication version, clear the current browser credentials, and require a fresh login. They do not
silently establish a replacement session.

Until a later milestone introduces MFA, passwords require at least 15 Unicode characters and permit at
least 64, including spaces, paste, autofill, and password-manager use. They are NFC-normalized before
hashing and checked against common, context-specific, and breached-password blocklists. The policy imposes
no character-class composition or periodic rotation. Argon2id is preferred where available, with deployment-
benchmarked cost and rehash-on-success. MFA, recovery, and step-up authentication are deferred beyond
`0.1.0` behind extensible authentication-method boundaries.

Authentication mutations validate an exact allowed Origin and credentialed CORS policy. Cookie-authorized
refresh and logout use the framework's synchronizer-token or cookie-to-header CSRF protection; login also
requires a custom same-origin header to prevent login CSRF. SameSite and Fetch Metadata remain defense in
depth rather than sole controls.

Login, activation resend, and password reset are throttled by normalized account identity and source signal
with generic public responses, comparable timing, retry-after outcomes, monitoring, and audit-safe events.
An unauthenticated attacker cannot permanently lock an account by submitting bad credentials. CAPTCHA and
external risk scoring remain replaceable starter integrations.

API access tokens and realtime subscription credentials use separate purpose-specific validation and
prefer separate asymmetric keypairs. Validators bind exact token type, issuer, audience, allowed algorithm,
time claims, subject, token ID, and key ID, and accept overlapping public keys only for a bounded rotation
period. An API access token is never sent directly to Mercure or treated as a Reverb channel authorization.

Each starter includes a minimal user-facing Active Sessions journey. It lists the current and other
sessions using a coarse browser/device label, creation time, last activity, and remembered status, and lets
the user revoke one other session or all other sessions. Metadata is descriptive rather than verified
device identity, and credential hashes and detailed location history are never exposed.

Each starter also lets `ROLE_SUPER_ADMIN` inspect and revoke another user's sessions through the dedicated
`MANAGE_USER_SESSIONS` permission. `ROLE_ADMIN` does not receive this permission. Every administrative
session revocation records the actor, target user, affected session scope, time, and non-secret reason or
context in the audit log.

Activation and password-reset email delivery are required package journeys. The package owns changeable,
invocation-neutral delivery subscribers and their use cases; each project owns sender identity, URL
generation, its template-engine and mail-service adapters, invocation routing, queueing, deployment
configuration, and unrestricted plain-text
or HTML templates. AccessControl documents and supplies a constrained template model containing safe user
display values, one-time action URL, and expiry. It does not pass aggregates, password hashes, credential
digests, or a general security context into templates. Public reset responses do not disclose whether an
address belongs to an eligible user.

Password-reset requests use the same secret-free delivery boundary as activation. The public
endpoint always returns accepted; eligibility and delivery outcomes remain internal and non-sensitive for
missing, deleted, disabled, or otherwise ineligible accounts.

An eligible `DISABLED` user may complete password reset, but remains `DISABLED`, receives no session, and
cannot authenticate until explicitly enabled. A deleted user receives no reset grant, and a
`PENDING_ACTIVATION` user completes activation instead.

User registration remains durably `PENDING_ACTIVATION` when activation-email delivery fails. The failed
attempt is observable to the inviting administrator and retryable. A rate-limited resend revokes the
predecessor activation grant and sends a replacement; it does not create another user or claim that the
original delivery succeeded.

The invitation or reset command atomically commits the User change, grant digest, and a pending security-
email delivery record. The delivery record holds the raw credential only as authenticated ciphertext under
a key kept outside the database. Its post-commit event identifies the exact delivery record rather than
only the user. The invocation-neutral subscriber loads that record and safe user data, decrypts only in memory,
builds the action URL, renders the project-owned template, and invokes the project mail service. Confirmed
delivery destroys the ciphertext. Failed delivery retains it only for bounded retry/dead-letter handling;
terminal expiry destroys it and requires a resend with a new grant. A pending record remains discoverable
if post-commit event dispatch fails, so durable registration cannot silently lose its delivery work.

Canonical email is unique across `PENDING_ACTIVATION`, `ACTIVE`, `DISABLED`, and soft-deleted users. Consumer
persistence enforces the constraint in addition to Application preflight. Reinviting a pending identity
uses the rate-limited resend journey; active or disabled identities are not duplicated, and deleted
identities require audited restoration. Public endpoints retain generic responses, while an authorized
administrator may receive the state-specific conflict. User identity is a stable `UserId` independent of
email, and relationships never use email as a foreign identity. The single-tenant unique constraint remains
an isolated consumer-owned persistence rule so a future multi-tenant project can replace it with
`(tenant_id, canonical_email)` without rewriting user/session/authorization identity.

Changing a login email is a dedicated request-and-confirm journey, never a generic User update. The
existing email remains authoritative while a single-use grant is delivered to the canonically reserved new
address. Confirmation changes the email, advances authentication version, and revokes every session;
cancellation or expiry releases the reservation. A super administrator may initiate the journey but cannot
bypass confirmation by the new address.

A user-initiated email-change request requires the current password. A super-admin initiation instead
requires its dedicated permission, atomic security audit, and a reason. Both paths notify the old mailbox,
and completion sends another old-mailbox security notice. `EmailChangeGrant` is purpose-bound and cannot be
used as an activation or password-reset credential. One grant may be active per user and one reservation per
canonical target address; replacement revokes the predecessor. Existing users in any account state outrank
reservations. Requests are throttled by actor, source, and target, use a configurable expiry with a 24-hour
starter default, and support self-cancellation plus audited super-admin cancellation.

Only an `ACTIVE` user may request or confirm email change. Disablement cancels any pending email-change
grant and releases its reservation. Pending users use invitation correction, while deleted users must be
restored before any email-change journey.

Correcting a pending invitation email is a dedicated, audited, super-admin-only command. It atomically
changes the pending user's canonical email, revokes the activation grant and pending delivery, releases the
old address, and creates a fresh activation grant and delivery for the corrected address. It neither
activates the user nor reuses the old credential.

Domain and Application code contain no Doctrine Collections, Symfony Security interfaces, or token
storage. Public collection snapshots use Fight collection contracts, and persistence adapters translate
their native records or collections at the boundary.

Managed Roles are stable-UUID reference data with exact declared permission membership. Reconciliation
removes undeclared assignments from Managed Roles while preserving consumer-created Roles and Permissions.
The initial managed set is expected to remain small, centered on `ROLE_SUPER_ADMIN` and
`ROLE_ADMIN`. Every project-managed permission is assigned to `ROLE_SUPER_ADMIN`; only permissions
explicitly classified as admin-safe are also assigned to `ROLE_ADMIN`. Neither role bypasses permission
checks. `ROLE_ADMIN` has read-only access to users, roles, and permissions; creating, updating, deleting,
or assigning those access-control records remains super-admin-only. Other destructive or security-
governance capabilities, including enabling sensitive integrations, also remain super-admin-only.

Administrative tier belongs to the version-controlled Managed Permission definition, not a database column
on the Permission aggregate. Every Managed Permission declares `ADMIN_SAFE` or `SUPER_ADMIN_ONLY`;
reconciliation derives both Managed Roles and rejects missing classification before mutation. Feature
tickets and pull requests introducing a permission must name its tier, while automated definition
validation is the enforcing authority. Persistence stores the resulting Permission, Role, and membership
records only.

Activation and password reset use distinct `ActivationGrant` and `PasswordResetGrant` contracts. They may
share internal credential-generation and hashing machinery, but their purpose, permitted state transition,
expiry, consumption, and revocation are not interchangeable.

Starter expiry defaults are 72 hours for activation, one hour for password reset, and 24 hours for email
change. Deployments may configure them. Each purpose permits one current grant per user; resend or rerequest
revokes its predecessor. The authoritative grant stores only a credential digest, consumption is atomic,
and public expired/consumed outcomes remain generic.

User disablement is reversible; user deletion is a protected soft delete. Both revoke every session, while
deletion also revokes every outstanding grant and preserves a tombstone identity for audit and historical
references. A deleted user may be recovered only through a distinct audited super-admin restoration
process; ordinary enablement cannot restore it, and no session or grant is resurrected. The restoring
super administrator explicitly selects `ACTIVE` or `DISABLED` and supplies a reason. Active restoration is
for a confirmed accidental deletion and retains the existing password while
still requiring a fresh login. Disabled restoration requires the normal password-reset/reactivation and
enablement flow. Both modes advance authentication version and record actor, target, selected state, reason,
and time. Personal-data retention, anonymization, or legal erasure is a separate consumer-owned workflow.
`DELETE_USERS` and `RESTORE_USERS` remain super-admin-only.

Managed Roles and Managed Permissions are readable but immutable through runtime administration. Their
stable identity, names, administrative tiers, and memberships change through version-controlled definition
review, deployment, and reconciliation. Super administrators may create and manage custom Roles and
Permissions, which reconciliation preserves.

The Managed Permission definitions must exactly match the named permission checks in consumer code. Every
check references a typed Managed Permission symbol rather than a free-form managed name, and project static
analysis compares definition and authorization-check references in both directions. Removing a Managed
Permission therefore requires proof that no code path still checks it. Reconciliation then removes its
Managed Role assignments and live Permission record. Historical audit evidence retains the
permission name as immutable event data rather than requiring the live record to remain. A custom Role or
Permission cannot be deleted while assigned or referenced; deletion never cascades, and the super
administrator must explicitly detach or reassign every dependency first.

Permission creation and removal remain explicit Application commands usable by reconciliation, project
seeders, tests, and developer CLI tooling. The starter does not expose generic Permission mutation through
its React administration UI: a database Permission without a corresponding authorization check grants no
useful capability. Custom Role administration remains available so projects can compose domain-specific
Roles from existing Permissions.

Managed Role/Permission reconciliation completes all collision and reference preflight before mutation,
then applies one UnitOfWork. Duplicate IDs or names, a name owned by a different ID, unknown Permission
references, or code/definition parity failures reject the complete operation. Stable ID plus a changed name
is an intentional rename. For each Managed Role, Fight Common `HashSet` difference derives
`permissionsToAdd` and `permissionsToRemove` from its persisted and defined memberships. Dry-run returns the
same deterministic ordered plan and issue set without writes, events, audits, or commit. An approved Managed
Permission removal is explicit in that plan.

Role and Permission names are opaque portable values. The initial managed names `ROLE_SUPER_ADMIN` and
`ROLE_ADMIN` retain their established spelling, but AccessControl does not parse `ROLE_`, infer hierarchy,
or grant authority from a prefix. Managed Permission names remain uppercase underscore identifiers, and
custom names obey the same validation rather than introducing framework-specific naming behavior.

The `0.1.0` aggregate boundary contains `User`, `Role`, `Permission`, `ActivationGrant`,
`PasswordResetGrant`, `EmailChangeGrant`, and `RefreshSession`. Access JWTs, realtime credentials,
email-delivery attempts, browser labels, and audit entries are application results, delivery records, or
infrastructure projections rather than aggregates.

The `0.1.0` command surface names security effects explicitly: invitation, pending-email correction,
activation, resend,
disablement and enablement, deletion and restoration, password change and reset, email-change
request/confirmation/cancellation, role assignment, session revocation, and Managed Role/Permission
reconciliation. It does
not collapse account transitions into a generic state-update command. Command-side repositories work with aggregates; queries return immutable `UserView`,
`RoleView`, `PermissionView`, `SessionView`, and paginated read models without secret material.

Fight AccessControl depends on the compatible Fight Common line for neutral values, messaging,
collections, pagination, UnitOfWork, mail, templating, and related ports. It does not duplicate those
primitives or require a framework. It contains only Domain and Application code and has no Adapter layer.
AccessControl owns typed `PlainPassword`, `PasswordHash`, and `PasswordVerification` boundaries while
consumer-owned adapters compose Fight Common 1.x string-based password services. Fight Common 1.x remains
compatible; adopting typed password signatures directly is a Fight Common 2.0 compatibility decision.
Application handlers may create access-token material and `RefreshSession` aggregates through these ports
and use cases. Command handlers and event subscribers do not know whether a consumer invokes them
synchronously or asynchronously; routing, queueing, retry, and worker policy are consumer responsibilities.
Test-only `InMemoryRepositories` may support AccessControl's own tests without becoming shipped runtime
adapters.

The certified client profile is the shared access-JWT/refresh-session design. A consumer project may
substitute its framework-native cookie session as an optional integration, but it does not create a second
AccessControl model and must pass the same shared-store, multi-device, revocation, CSRF, session-management,
and audit behavior suite.

Refresh credentials rotate once per successful refresh through an atomic compare-and-swap. When two
legitimate requests race with the same credential, the winner consumes it and installs its successor; an
immediate loser within a short configurable conflict window receives only a `refresh_conflict` outcome,
does not receive a credential, and does not revoke the session. The client waits for the winning browser
context to announce completion and retries once with the installed successor. Reuse outside the conflict
window revokes that device session family. Service Worker or Web Lock coordination and BroadcastChannel
notification reduce legitimate conflicts without making the old credential generally reusable.

Every full SPA load begins without an access JWT and awaits session refresh before rendering authenticated
UI or starting protected requests. A valid refresh session returns a new 15-minute access JWT and the
authoritative principal. The client treats the token as needing refresh at ten minutes old, five minutes
before expiration. Requests await one module-wide refresh promise; an eligible authorization failure may
cause one refresh and one replayable retry. Bounded server/client clock-offset calculation protects the
safety boundary without permitting unbounded leeway.

Realtime application delivery is private by default. Anonymous subscriptions are disabled, and user,
account, job, administration, and personalized updates require exact server-authorized topic or channel
rights. Public updates require an explicit declaration that their underlying information is already
available without authentication; a topic name or user UUID is never treated as a secret. Laravel uses
Reverb private-channel authorization by default, while Symfony, Yii, CodeIgniter, and Slim use current
Mercure private-update authorization by default.

Fight Common 2.0 replaces the public-only `Publisher::push(topic, message)` abstraction with an explicit
realtime update contract that cannot omit public/private visibility and a separate subscription-
authorization port. Provider adapters translate that portable behavior to Reverb or Mercure.

Realtime subscription credentials expire after at most five minutes and require reauthorization.
Application-session revocation immediately prevents renewal. Provider adapters terminate an existing
connection sooner when supported, but the portable contract states the five-minute maximum residual
connection window honestly. Realtime payloads carry minimal invalidation or notification data; sensitive
state is refetched through the API boundary, where revocation is immediate.

Each domain event selected for browser publication has one dedicated Application transformer rather than a
central event-type switch. The transformer maps that event to a versioned public realtime envelope with a
stable public name, message identity, occurrence time, authorized topic, allowlisted payload, and allowlisted
metadata. Internal `Message` serialization, PHP payload FQCNs, arbitrary metadata, and non-public event fields
are not browser contracts. Framework composition collects transformer services using its native deterministic
registration style; Slim registers them explicitly. OpenAPI generates HTTP view types for the editable React
client, while versioned realtime JSON Schemas generate its discriminated event-envelope union. Build checks
reject generated TypeScript drift.

State commits precede event publication. A publication failure after a successful commit is an
infrastructure failure after durable success, not a rollback or an implied atomic-delivery guarantee.

Required security auditing is the narrow exception. Administrative session revocation, user
deletion/restoration, Role or Permission mutation, and other classified sensitive commands do not succeed
unless their typed, secret-free audit record is durably committed with the mutation. The record contains
actor, target, action, outcome, UTC time, correlation identity, affected session or delivery identities when
relevant, and any required administrative reason. Request IP address and user-agent enrichment are
consumer policy rather than portable Domain data. When one consumer-owned transaction cannot cover the
chosen stores, that project must atomically persist a durable audit handoff/outbox with the mutation.
Ordinary Domain-event publication retains the commit-then-publish rule above.

## Consequences

Consumer-owned shared-store implementations must model independent session identity, opaque credential digests, rotation lineage,
expiry, trusted remember-me policy, and revocation so refresh, reuse detection, logout scope, and global
revocation are atomic across application container instances. Database and Redis implementations are both
valid when they provide the required compare-and-swap or locking behavior and expiry semantics. Framework
adapters own cookies and HTTP integration, but every starter must prove the same behavior.

The design provides the required invite, email, set-password, short access-JWT, refresh-cookie, and
remember-me experience while making account state, credential storage, session scope, persistence policy,
and atomic rotation explicit.

Storing the recoverable raw credential directly on the authoritative grant was rejected because a database
disclosure would expose immediately usable activation and reset links. Hash-only storage without
a delivery envelope was rejected because a later-invoked subscriber could not reconstruct the credential
for rendering or bounded retries.

Immediate concurrent refresh does not prove credential theft, so revoking a session on every losing race
was rejected. A long half-life during which an old credential remains generally accepted was also rejected
because it extends replay value. The bounded conflict outcome preserves normal multi-tab behavior without
returning credentials to the losing request.

Public-by-default realtime delivery was rejected because frontend code cannot prevent a client from
connecting directly to a discoverable public topic. Payload minimization remains required defense in depth,
but hub or channel authorization is the confidentiality boundary.

A mutable database classification for Managed Permissions was rejected because administrative seeding is
project governance and must be reviewed and versioned with the Managed Permission definitions. A process-only checklist
without automated validation was rejected because it can silently omit or over-grant a new permission.

Physical deletion in the generic AccessControl use case was rejected because audit and historical
references require stable identity. Treating deletion as ordinary reversible disablement was rejected
because it would let routine administration resurrect an intentionally removed account; recovery instead
uses an explicit super-admin permission and audit trail.

Always restoring a deleted user to one fixed state was rejected. Forcing `DISABLED` adds unnecessary
password-reset friction after a confirmed accidental deletion, while always restoring `ACTIVE` is too
permissive for an intentionally deleted or potentially compromised account. Explicit restoration mode,
reason, and audit make the risk decision visible. A separate password-only step-up ceremony is deferred
until the later MFA and step-up authentication design can define it consistently.

## Rejected Alternatives

Immediate active registration was rejected because it does not model the desired invitation and first-
password journey. Treating a nullable password as account state was rejected because it hides lifecycle
rules and permits ambiguous authentication behavior.

A single refresh token per user was rejected because logging in or out on one browser would invalidate
unrelated remote devices. Stateless long-lived JWTs were rejected because they cannot provide reliable
per-session revocation, reuse detection, or current account-state enforcement.

Keeping session management exclusively operational was rejected because users would have no direct way to
respond to a lost or unfamiliar device, and the starter would not prove its multi-session contract. A rich
device-fingerprinting product was also rejected; the required interface is a small security journey over
coarse session metadata.

Framework-native server sessions as the only required profile were rejected because the five SPA starters
need one demonstrable HTTP authentication journey. They may remain supported alternative compositions.
