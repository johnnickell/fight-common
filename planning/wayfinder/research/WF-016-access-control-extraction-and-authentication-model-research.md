# WF-016 AccessControl extraction and authentication model research

**Research date:** 2026-08-13

**Ticket:** [WF-016](../tickets/WF-016-access-control-extraction-and-authentication-model.md)

**Dependencies:** [WF-012](../tickets/WF-012-access-control-and-persistence-boundaries.md), [WF-014](../tickets/WF-014-fight-common-contract-and-compatibility-audit.md)

**Boundary:** Research and recommendation inputs only. This document does not decide the ticket, move source, define a released API, or derive proprietary business behavior.

## Executive finding

The strongest portable baseline is a synthesis rather than a copy of one source:

- Omphalos has the best dependency direction: an Application `CurrentUserProvider` is implemented by a Symfony token-storage adapter. Its remaining flaw is that the neutral port returns the domain `User` aggregate rather than an immutable principal (`Omphalos: src/Application/AccessControl/Security/CurrentUserProvider.php:5-17`; `src/Adapter/Security/AccessControl/TokenStorageCurrentUserProvider.php:5-37`).
- Fight CMS has the best focused Managed Role/Permission reconciliation: stable IDs, rename-by-ID, collision checks, exact role membership, dry-run, custom-record counting, and one UnitOfWork commit (`fight-cms: src/Application/AccessControl/ReferenceData/ReferenceDataSynchronizer.php:20-167`). Its file parsing, aggregate-bearing result, 1,000-record custom count, and public `$commit` switch are not suitable package contracts (`fight-cms: src/Application/AccessControl/ReferenceData/ReferenceDataSynchronizer.php:175-229`; `src/Application/AccessControl/ReferenceData/ReferenceDataSyncResult.php:13-95`).
- `project`, Fight CMS, and Omphalos share nearly the same User/Role/Permission model, but all three let Symfony Security and Doctrine Collections reach the domain. Omphalos already converts its internal Doctrine role collection to a Fight Common `ArrayList`, demonstrating the viable containment seam (`Omphalos: src/Domain/AccessControl/User/User.php:7-30,117-129,197-240`).
- The required two-credential journey—short access token plus HttpOnly refresh cookie—needs explicit protection against recoverable-token persistence, non-atomic rotation, refresh races, incomplete password-change revocation, and frontend refresh work that is neither awaited nor single-flight.

The recommended `0.1.0` core is framework-neutral Domain and Application code, with native cookie sessions as the required integration profile and rotating refresh families as an opt-in profile. Symfony/Laravel/Yii/CodeIgniter/Slim principal providers, voters, middleware, cookies, and session stores remain starter-owned adapters. The package should expose immutable Fight Common collection/read-model surfaces, not Doctrine collections or aggregates from queries.

## Evidence method and interpretation

This research compared live local snapshots of the three required sources and reviewed primary authentication/security standards. Graphify was used for orientation; file-level source inspection is authoritative. WF-014 and WF-015 were used only for document shape, never as primary evidence.

Classification used below:

- **Observed** — behavior present in a cited local source or primary external source.
- **Inferred design input** — consequence derived from observed behavior; not an accepted contract.
- **Recommendation** — proposed input to WF-016 resolution.
- **Unresolved** — evidence does not select one safe contract.
- **Ticket-owned decision** — WF-016 must explicitly accept, change, or reject the recommendation before specification work.

## 1. Source comparison and extraction boundary

### Common behavior worth extracting

**Observed.** The three sources converge on `User`, `Role`, and `Permission` aggregates; typed UUID identifiers and names; user registration/removal/password reset and role assignment commands/events; role/permission definition and assignment; repositories by ID/name/email plus paginated listing; and permission derivation through assigned roles. Omphalos shows the complete shape: `User::hasPermission()` traverses roles, while `getPermissions()` de-duplicates permission names (`Omphalos: src/Domain/AccessControl/User/User.php:149-179`). Its User persistence mapping contains identity, email, names, password, timestamps, reset-token fields, and roles, but no account-state field (`Omphalos: database/schemas/AccessControl.User.User.orm.xml:6-27`).

**Observed.** Existing command handlers add or mutate aggregates, commit once, then dispatch an event; registration is representative (`Omphalos: src/Application/AccessControl/User/Command/RegisterUser/RegisterUserHandler.php:21-75`). Existing query handlers return mutable aggregates directly (`Omphalos: src/Application/AccessControl/User/Query/GetUserById/GetUserByIdHandler.php:17-52`).

**Recommendation.** Extract only generic identity, credentials, account state, roles, permissions, authentication, authorization, session revocation, and Managed Role/Permission reconciliation. Exclude Omphalos `Agent`, persona/pantheon/calendar/task catalogs, CMS capabilities, and unrelated business objects. Do not make a general “security framework”; publish the smallest coherent AccessControl vertical slice.

### Framework and persistence dependency direction

**Observed.** The current Omphalos `User` imports and implements Symfony user interfaces, and stores roles in Doctrine `Collection` (`Omphalos: src/Domain/AccessControl/User/User.php:7-30,197-240`). `project` and Fight CMS have the same coupling (`project: src/Domain/AccessControl/User/User.php:7-25`; `fight-cms: src/Domain/AccessControl/User/User.php:7-25`). `project` and Fight CMS also inject Symfony `TokenStorageInterface` into Application services (`project: src/Application/AccessControl/Feature/FeatureService.php:10-22`; `fight-cms: src/Application/AccessControl/Feature/FeatureService.php:10-22`).

**Recommendation.** Domain and Application must have no Symfony Security imports and no token-storage concept. Define neutral principal/current-principal/authorization ports. A starter adapter may implement them with Symfony token storage and voters, as Omphalos already demonstrates (`Omphalos: src/Adapter/Security/AccessControl/UserPermissionVoter.php:7-55`). Framework user/provider interfaces belong on adapter-specific principal wrappers, never on `User`.

**Recommendation.** Doctrine Collections may remain a private persistence implementation detail only if Doctrine can hydrate them without changing public signatures. Public aggregate methods return `Fight\Common\Domain\Collection\ArrayList<T>` for ordered snapshots or `HashSet<T>` for unique sets; callers cannot receive or mutate a Doctrine `Collection`. Prefer intention methods (`assignRole`, `removeRole`, `grantPermission`, `revokePermission`) over writable collection access. If Doctrine cannot map the private collection without annotations/interfaces leaking into Domain, starter adapters must map persistence records to framework-neutral aggregates.

**Ticket-owned decision.** Accept private Doctrine collection containment for `0.1.0`, or require fully persistence-ignorant arrays/Fight collections immediately. The former is the lower-risk extraction and still preserves the public boundary mandated by WF-012.

## 2. Candidate `0.1.0` Domain and Application surface

The names below are recommendation inputs, not an approved API.

### Domain model

| Contract | Recommended responsibility | Explicit exclusion |
|---|---|---|
| `User`, `UserId` | Identity, normalized unique email, display names, opaque `PasswordHash`, `AccountState`, role assignments, timestamps, monotonic `authenticationVersion` | Symfony interfaces, raw password, raw reset/refresh token, request/session object |
| `AccountState` | `ACTIVE` or `DISABLED`; authentication and authorization deny disabled users | Temporary brute-force throttling/lockout; invitation/verification until a consumer proves the need |
| `Role`, `RoleId`, `RoleName` | Named permission set, rename, grant/revoke | Framework role prefixes such as `ROLE_` |
| `Permission`, `PermissionId`, `PermissionName` | Stable named capability and rename | Route/controller knowledge |
| `PasswordResetGrant`, `PasswordResetGrantId` | User ID, token digest, issued/expiry/consumed/revoked timestamps | Recoverable token value |
| `RefreshSession`, `SessionId`, `SessionFamilyId` | Optional-profile refresh family member, digest, expiry, used/revoked/replacement state | JWT implementation, cookie API, IP/device fingerprint authority |

**Inferred design input.** Existing raw password-reset token fields make a database leak immediately actionable (`Omphalos: src/Domain/AccessControl/User/User.php:31-32,242-274`). A separate one-time grant aggregate supports hashing, expiry, audit, and atomic consumption without bloating `User`.

**Recommendation.** `AccountState` starts with only `ACTIVE` and `DISABLED`. Temporary lockout is an authentication-policy concern, not durable account truth. `disable()` increments `authenticationVersion` and emits `UserDisabled`; `enable()` does not resurrect existing sessions. Every password change/reset increments the version. This gives session/JWT adapters one neutral revocation epoch.

**Ticket-owned decision.** Whether registration creates `ACTIVE` immediately or needs a later `PENDING_VERIFICATION` state cannot be inferred from the common sources. Keep verification out of `0.1.0` unless a starter requirement makes it part of the release contract.

### Commands and events

**Recommendation.** First-package commands:

- Users: `RegisterUser`, `ChangePassword`, `RequestPasswordReset`, `CompletePasswordReset`, `DisableUser`, `EnableUser`, `AssignRoleToUser`, `RemoveRoleFromUser`.
- Roles: `DefineRole`, `RenameRole`, `RemoveRole`, `GrantPermissionToRole`, `RevokePermissionFromRole`.
- Permissions: `DefinePermission`, `RenamePermission`, `RemovePermission`.
- Sessions: `RevokeSession`, `RevokeAllUserSessions`; optional stateless profile adds `AuthenticateCredentials`, `RefreshAccess`, and `LogoutSession` use cases.
- Managed access: `ReconcileManagedRolesAndPermissions` with a dry-run mode.

Events mirror successful state changes and contain only typed IDs, non-secret names/state, occurrence time, and correlation metadata. Password hashes, reset/refresh credentials, cookies, JWTs, and full user serialization never appear in events or logs.

**Recommendation.** Do not expose physical `RemoveUser` in `0.1.0`; disable identities so audit references and authorization history remain stable. Role/permission removal must fail when referenced unless a deliberate replacement/migration is supplied.

**Unresolved / ticket-owned decision.** Current handlers publish after commit, so publication failure can be reported after durable state change. WF-016 must choose the existing commit-then-publish contract or require an outbox before claiming atomic state/event delivery. This research recommends documenting commit-then-publish in `0.1.0` and opening an outbox follow-up rather than silently implying atomicity.

### Queries, read models, repositories, and UnitOfWork

**Recommendation.** Queries are `GetUserById`, `GetUserByEmail`, `ListUsers`, `GetRoleById`, `GetRoleByName`, `ListRoles`, `GetPermissionById`, `GetPermissionByName`, `ListPermissions`, and `GetCurrentPrincipal`. List results carry records and total/page metadata, making separate count queries unnecessary.

Query handlers return immutable `UserView`, `RoleView`, `PermissionView`, `AuthenticatedPrincipal`, or `Page<T>`, never aggregates. `UserView` includes ID, email, names, account state, role names, permission names, version, and timestamps but no password/reset/session material. The current aggregate-returning query is evidence for the boundary that needs correction, not a contract to preserve (`Omphalos: src/Application/AccessControl/User/Query/GetUserById/GetUserByIdHandler.php:17-52`).

Repositories remain Domain/Application ports and return aggregates to command-side services: `find(UserId)`, `findByEmail(EmailAddress)`, `save(User)`, and paginated iteration; equivalent ID/name operations apply to Role and Permission. Adapter repositories own ORM query builders and identity-map behavior. No abstract repository base class is part of the public package.

Fight Common already provides `UnitOfWork::commit()`, `commitTransactional(callable)`, and `isClosed()`; its Doctrine adapter delegates the transactional operation to the entity manager (`fight-common: src/Application/Repository/UnitOfWork.php:7-32`; `src/Adapter/Repository/DoctrineUnitOfWork.php:10-42`). Command handlers should stage through repositories and commit once. Cross-aggregate Managed Role/Permission reconciliation should use one `commitTransactional()` boundary; dry-run uses zero writes and zero commits.

### Password, principal, and authorization ports

**Observed.** Fight Common already owns framework-neutral `PasswordHasher` and `PasswordValidator` ports with PHP adapters (`fight-common: src/Application/Auth/Security/PasswordHasher.php:5-19`; `src/Application/Auth/Security/PasswordValidator.php:5-20`; `src/Adapter/Auth/Security/PhpPasswordHasher.php:13-40`; `src/Adapter/Auth/Security/PhpPasswordValidator.php:12-42`).

**Recommendation.** Preserve those capabilities while refining the AccessControl-facing vocabulary:

- `PasswordHasher::hash(PlainPassword): PasswordHash` and `PasswordVerifier::verify(PlainPassword, PasswordHash): PasswordVerification`, where the result includes `valid` and `needsRehash`.
- `PasswordPolicy` validates new passwords independently of hashing. Deployment configuration selects a modern password-hashing algorithm and work factor; salts are library-managed. OWASP prefers Argon2id when available and recommends increasing work factors over time ([OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html#password-hashing-algorithms)).
- `AuthenticatedPrincipal` is an immutable Application value containing `UserId`, account state/version, role-name snapshot, permission-name snapshot, and authentication time. It carries no password and implements no framework interface.
- `CurrentPrincipalProvider::current(): ?AuthenticatedPrincipal` replaces shared token storage.
- `AuthorizationChecker::isGranted(AuthenticatedPrincipal, PermissionName, ?AuthorizationContext): bool` denies by default and validates permission against authoritative state for the request. Object-level policy remains consumer-owned; a permission name alone does not solve IDOR/BOLA. OWASP recommends least privilege, deny by default, and validating permission on every request ([OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html#authorization-cheat-sheet)).

**Ticket-owned decision.** Decide whether a principal snapshot may authorize ordinary requests until its session version is revalidated, or every authorization check reloads current roles/permissions. Recommendation: reload/revalidate on each request at the adapter boundary, then use the immutable snapshot within that request.

## 3. Managed Role and Managed Permission reconciliation

### Observed behavior

Fight CMS accepts hard-coded permission and role fixture paths, validates duplicate fixture IDs/names, resolves records by both ID and name, permits rename by stable ID, rejects a name mapped to a different ID, and reconciles fixture-role membership exactly (`fight-cms: src/Application/AccessControl/ReferenceData/ReferenceDataSynchronizer.php:40-130,175-202`). It counts non-fixture records but only across one 1,000-item page (`fight-cms: src/Application/AccessControl/ReferenceData/ReferenceDataSynchronizer.php:204-229`). The result includes counts and a map of mutable Role aggregates (`fight-cms: src/Application/AccessControl/ReferenceData/ReferenceDataSyncResult.php:13-95`).

Omphalos repeats the same AccessControl collision and membership rules inside a broader, project-specific reconciler (`Omphalos: src/Application/ReferenceData/Service/ReferenceDataSynchronizer.php:240-333`). It improves custom-record counting by paging through all permission and role records (`Omphalos: src/Application/ReferenceData/Service/ReferenceDataSynchronizer.php:1135-1182`) but mixes unrelated catalogs and exposes a `$commit` mode (`Omphalos: src/Application/ReferenceData/Service/ReferenceDataSynchronizer.php:560-632`).

### Recommended portable contract

**Inputs.** Typed Managed Permission definitions contain stable ID, name, and administrative tier; typed Managed Role definitions contain stable ID, name, and permission names. The package accepts these values; JSON/YAML/filesystem parsing and schema diagnostics belong to consumer projects.

**Stable UUID ownership.** The project defining a Managed Role or Managed Permission allocates and versions its UUID. Reconciliation never generates replacement IDs. An existing ID with a new name is a rename. Removal is an explicit reconciled operation after code/definition parity validation.

**Preflight and collisions.** Before any mutation, reject duplicate input IDs, duplicate input names, unknown role permission references, invalid UUID/name values, and the case where requested ID and requested name resolve to different persisted records. Also reject a requested name already owned by another ID. Return a deterministic issue set; do not partially apply a valid prefix.

**Ownership and custom records.** Records whose stable IDs are absent from the Managed Role/Permission definitions are custom and are never renamed or removed. A Managed Role's permission membership is exact desired state: extra assignments—including a custom Permission assigned to that Managed Role—are removed. Custom Roles and all their assignments remain untouched. Fight Common `HashSet` differences derive permissions to add and remove. The result reports preserved custom record counts without loading them all into a public collection.

**Dry-run and result.** Dry-run computes the same deterministic reconciliation plan and collision failures as apply, but performs no aggregate mutation, repository write, event publication, audit, or UnitOfWork commit. The immutable result contains mode, created/renamed/unchanged counts by type, assignments added/removed, custom records preserved, and ordered action/issue descriptors made only of IDs/names—not aggregates.

**UnitOfWork.** Apply performs preflight first, stages all changes, and commits exactly once inside `commitTransactional()`. Any collision or write failure rolls back the entire reconciliation. The public use case has no `$commit=false` escape hatch; consumers needing a larger transaction call an internal domain planner and own an explicit orchestration boundary rather than weakening default atomicity.

**Ticket-owned decision.** Confirm the exact-membership treatment of custom Permissions on Managed Roles. It is consistent with current CMS/Omphalos behavior, but it is the one preservation rule with legitimate migration consequences.

## 4. Required cookie-session profile

**Recommendation.** The required/default profile is a framework-native opaque server-side session:

1. Credentials enter an Application authentication use case; raw passwords are not retained.
2. On success, the adapter regenerates the session identifier and stores only user ID, authentication version, and minimal session metadata. It reloads an active authoritative principal per request.
3. The cookie uses a `__Host-` name, `Secure`, `HttpOnly`, `Path=/`, no `Domain`, and an explicitly selected `SameSite` policy. `Lax` is the interoperability default; `Strict` is preferable where every intended flow tolerates it. OWASP documents Secure, HttpOnly, SameSite, narrow scope, and session-ID regeneration as core session protections ([OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#cookies)).
4. Every state-changing request, including login/logout where applicable, receives framework CSRF protection. SameSite is defense in depth, not the sole control. Use synchronizer tokens or an appropriate custom-header pattern and validate request Origin against a strict allowlist ([OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#introduction)).
5. Idle and absolute expiry are deployment policy. Disable, password change/reset, and logout-all revoke registered sessions and increment `authenticationVersion`; current-session logout revokes only that session.

The package owns authentication and revocation intents plus neutral principal/authorization contracts. Each starter owns its session store, cookie configuration, authenticator/user-provider bridge, CSRF facility, middleware/voter/policy adapter, response transport, and route wiring. No Symfony session, `TokenStorageInterface`, PSR request, or framework cookie type crosses into shared Domain/Application.

**Ticket-owned decision.** Native session stores cannot all provide a portable searchable session registry. Each certified starter must either implement the package `SessionRegistry` port or document that `authenticationVersion` validation is the revocation mechanism. The package must test behavior, not prescribe one storage engine.

## 5. Optional stateless access profile

This is an opt-in first-party web profile, not a claim of OAuth conformance. RFC 9700 is used as high-quality token replay guidance.

### Credential and storage model

**Product input.** The required client journey uses a roughly 15-minute access token held in frontend
memory, an HttpOnly refresh cookie, and returned identity/Role/Permission display data. The portable design
must specify non-recoverable authoritative storage and atomic one-time refresh rotation rather than leaving
those properties to a consumer implementation.

**Recommendation.** Issue a short-lived access JWT (5–15 minutes) to JavaScript memory only. Required claims are algorithm-bound `iss`, `aud`, `sub`, `exp`, `iat`, `nbf` where used, and unique `jti`, plus `authenticationVersion` or session ID. Do not use mutable roles/permissions in the JWT as sole server authority. Verifiers explicitly allowlist algorithms, validate issuer/audience/type and all time claims, and use small bounded clock leeway. RFC 8725 requires algorithm verification and validation rules appropriate to each JWT kind ([RFC 8725 §§3.1, 3.8–3.12](https://www.rfc-editor.org/rfc/rfc8725.html#section-3)); RFC 7519 defines expiration/not-before processing and permits only small skew leeway ([RFC 7519 §§4.1.4–4.1.6](https://www.rfc-editor.org/rfc/rfc7519.html#section-4.1.4)).

Fight Common's current generic JWT decoder verifies a signature but does not itself enforce issuer, audience, expiry, not-before, or token purpose (`fight-common: src/Adapter/Auth/Security/JwtDecoder.php:22-70`). Therefore the profile needs purpose-specific `AccessTokenIssuer` and `AccessTokenVerifier` Application ports/adapters; the generic decoder alone is insufficient.

Generate at least 256 random bits for each opaque refresh credential. Send the raw value only in a `__Host-` Secure, HttpOnly, SameSite cookie (`Path=/`, no `Domain`). Persist only a keyed digest/HMAC plus `sessionId`, `familyId`, user ID, issued/expiry/used/revoked timestamps, replacement ID, and optional non-authoritative client label. A high-entropy token also makes SHA-256 viable, but keyed hashing limits the impact of implementation entropy mistakes; the key remains outside the database.

### Rotation, reuse, and revocation state machine

**Recommendation.** In one database transaction, lock/compare-and-swap the presented active refresh grant, mark it used, create exactly one successor in the same family, and issue the new access token. Presenting an expired, revoked, or already-used ancestor is reuse: revoke the active family and emit a security audit event. RFC 9700 specifies rotation by issuing a new refresh token, invalidating the previous token, retaining their relationship, and revoking the active token when reuse reveals compromise ([RFC 9700 §4.14.2](https://www.rfc-editor.org/rfc/rfc9700.html#section-4.14.2)).

No permissive replay grace window is recommended by default. Legitimate concurrent refreshes would otherwise be indistinguishable from theft; client single-flight and cross-tab serialization prevent the common race. If operational evidence later requires idempotent replay, it needs its own threat model and must never return a recoverable successor from persistence.

Logout scope:

- ordinary logout revokes the current refresh family, clears the refresh cookie, and erases the in-memory access token;
- logout-all, disable, password change, and password reset revoke every family and increment `authenticationVersion`;
- already-issued access JWTs remain usable until expiry unless each request checks account/version state or a denylist. The profile must state this residual window honestly.

Password change requires current-password verification or equally strong recent reauthentication, commits the new hash and global revocation atomically, and does not silently create a replacement session. Password reset consumes a hashed single-use reset grant and revokes all sessions. Both effects must be explicit and covered independently.

### CSRF, Origin, and response handling

The refresh cookie is ambient authority, so refresh and logout require CSRF defense even though the access JWT uses an Authorization header. Require an unguessable CSRF value bound to the refresh session (or the framework's synchronizer pattern), a non-simple custom header, and exact allowlisted `Origin`; reject missing Origin in browser contexts unless a documented same-origin fallback is proven. SameSite and Fetch Metadata are defense in depth. OWASP recommends built-in framework protection, tokens/custom headers, Origin verification, and SameSite rather than relying on one control ([OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html#csrf-prevention-techniques)). Never put credentials in URLs, logs, error bodies, analytics, caches, or referrers.

## 6. Frontend contract

**Design risk.** Starting refresh during request-header construction without awaiting it allows the protected
request to race ahead with stale credentials. Per-request refresh work also permits duplicate rotations and
unbounded retry behavior. The certified client therefore needs one shared awaited refresh operation,
bounded replay, clock-offset handling, and a strict separation between client display state and server
authorization authority.

**Recommendation.** A certified optional-profile client must:

- maintain one module-wide refresh promise; all callers await it and it is cleared in `finally`;
- await proactive freshness before sending a request;
- on the first eligible 401, await one refresh and retry exactly once with a replayable request; never retry login/refresh/logout, never loop, and require an explicit body-replay policy for streams/uploads;
- derive server clock offset from trusted response time/issued-at plus client receipt time, clamp unreasonable offsets, honor small verifier leeway, and refresh within a configured safety window rather than hard-coding five minutes;
- bootstrap in a loading state by calling `/session` or refresh-backed `/me`; a page reload has no access token, while the HttpOnly cookie may still establish identity;
- serialize refresh across tabs with a same-origin Web Lock when available. Use `BroadcastChannel` only for non-secret “session changed”, “refresh finished”, and “logout” signals; never broadcast refresh credentials and preferably never broadcast access tokens. The HTML Standard defines BroadcastChannel communication among same-origin browsing contexts ([HTML Standard, broadcasting](https://html.spec.whatwg.org/multipage/web-messaging.html#broadcasting-to-other-browsing-contexts)); Web Locks coordinates asynchronous work across same-origin tabs/workers ([Web Locks API](https://w3c.github.io/web-locks/#introduction));
- treat client role/permission data as display hints only. Every server operation performs authoritative authorization against the current active principal.

**Unresolved / ticket-owned decision.** Browsers without Web Locks need a documented fallback. Recommendation: serialize within each tab, accept that a cross-tab collision revokes the family, broadcast logout/re-authentication required, and avoid weakening server reuse detection. A more forgiving protocol needs separate security review.

## 7. Threat-model register

WF-016 should require dedicated tests/review for each topic below before the relevant profile is called certified:

| Threat | Required design/test input |
|---|---|
| Credential stuffing, brute force, enumeration, timing | Generic authentication/reset responses and comparable timing; per-account plus per-source throttling; monitoring and recovery that cannot lock out victims indefinitely. OWASP covers generic errors and login throttling ([OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html#authentication-responses)). |
| Password/database compromise | Memory-hard password hashing, library-generated salts, rehash-on-success, secret-free events/logs, reset grants hashed and single-use. |
| Session fixation/hijacking | Regenerate native session ID at authentication/privilege change; secure cookie scope; idle/absolute timeout; version validation. |
| CSRF, login CSRF, logout CSRF, CORS | CSRF token/custom header, exact Origin allowlist, no wildcard credentialed CORS, SameSite defense in depth, state-changing-method tests. |
| XSS/token exfiltration | Access token only in memory; refresh token HttpOnly; CSP/output encoding remain consumer controls; never put secrets in local/session storage. |
| JWT confusion and key lifecycle | Algorithm allowlist, token-type-specific validation, issuer/audience/time/subject checks, key ID/rotation procedure, fail closed on unknown key. |
| Refresh DB theft, replay, race, and DoS | Hashed storage, atomic single-use rotation, family graph, reuse-family revocation, concurrent refresh tests, audit without secret. Include the possibility that an attacker intentionally replays an old token to revoke a victim family. |
| Stale privilege/account state | Authentication-version checks, short JWT lifetime, session revocation on disable/password changes, server-side authorization every request. |
| IDOR/BOLA and confused deputy | Object-level consumer policy after coarse permission check; principal/action/resource test matrix; deny by default. |
| Managed-definition poisoning/collision | Typed parsing, duplicate and cross-record collision preflight, unknown-reference rejection, exact ownership rules, signature/provenance controls in distribution. |
| Partial/concurrent reconciliation | One transaction, database uniqueness constraints, race tests, deterministic dry-run/apply parity, rollback injection tests. |
| Cookie subdomain injection | `__Host-` cookie, no Domain, Secure, Path `/`; proxy/TLS configuration validation. |
| Clock and multi-tab races | Bounded skew, expiry-boundary tests, single-flight, cross-tab lock/fallback tests, no permissive infinite retry. |
| Logout/password semantics | Current versus all-session tests; cookie deletion symmetry; residual access-JWT window stated and tested. |
| Sensitive-data leakage | Redaction tests for logs, traces, exceptions, cache headers, analytics, URLs, and event payloads. |

## 8. Remaining unknowns and ticket-owned decisions

1. Private Doctrine collection containment versus immediate persistence-ignorant collections.
2. Whether registration needs a third account state such as pending verification.
3. Commit-then-publish events for `0.1.0` versus an outbox prerequisite.
4. Exact-membership removal of custom Permission assignments from Managed Roles.
5. Per-starter session registry versus mandatory authentication-version lookup.
6. The exact access-JWT lifetime, refresh absolute/idle lifetimes, password work factor, throttling limits, and clock leeway; these require deployment performance/risk input, not hard-coded package constants.
7. Password-change UX after global revocation: recommendation is reauthentication rather than silent replacement.
8. Cross-tab fallback for browsers without Web Locks.
9. JWT signing algorithm and key distribution/rotation for the optional profile; asymmetric signing is preferable across separately deployed issuers/resource servers, while a single-process deployment may justify a tightly scoped symmetric key.
10. Whether `0.1.0` includes password-reset transport ports. Recommendation: package generates/consumes grants and emits a notification intent; mail/SMS/template delivery stays consumer-owned.

These are specification choices. None is resolved merely by this research artifact.

## Source ledger

### Local source snapshots

| Source | Snapshot | Scope reviewed |
|---|---|---|
| `fight-common` | `12bf25996ac2cafb12485850259a1276a88a6d57` | Auth/password/JWT ports and adapters, UnitOfWork, WF-012/WF-014/WF-016, existing research format |
| `project` | `c82e942668ad6b5975a3d9fa395631d3936dbb1c` | AccessControl Domain/Application baseline and Symfony/token-storage coupling |
| `fight-cms` | `94deae3d4fa9c2e691e2f85c5294b8f35ac1b6d4` | AccessControl model, security configuration, focused reference-data synchronizer/result/command |
| `Omphalos` | `80bf0ad393db52d24d31d4d87f655483cb376c5f` | AccessControl model, neutral current-user port plus Symfony adapters, handlers, persistence mappings, paginated reconciliation behavior |
### Primary external sources

All external sources accessed 2026-08-13.

1. IETF, [RFC 9700: Best Current Practice for OAuth 2.0 Security](https://www.rfc-editor.org/rfc/rfc9700.html), especially [§4.14.2 Refresh Token Protection](https://www.rfc-editor.org/rfc/rfc9700.html#section-4.14.2).
2. IETF, [RFC 8725: JSON Web Token Best Current Practices](https://www.rfc-editor.org/rfc/rfc8725.html), especially [§3 Best Practices](https://www.rfc-editor.org/rfc/rfc8725.html#section-3).
3. IETF, [RFC 7519: JSON Web Token](https://www.rfc-editor.org/rfc/rfc7519.html), especially [§§4.1.4–4.1.7 registered time and token-ID claims](https://www.rfc-editor.org/rfc/rfc7519.html#section-4.1.4).
4. OWASP, [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
5. OWASP, [Cross-Site Request Forgery Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).
6. OWASP, [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html).
7. OWASP, [Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html).
8. OWASP, [Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html).
9. WHATWG, [HTML Standard: Broadcasting to other browsing contexts](https://html.spec.whatwg.org/multipage/web-messaging.html#broadcasting-to-other-browsing-contexts).
10. W3C Web Platform Incubator Community Group, [Web Locks API](https://w3c.github.io/web-locks/).

## Resolution boundary

This artifact supplies evidence and recommendation inputs for every WF-016 “Must decide” item. The ticket must convert selected recommendations into the package extraction specification and authentication state model. No source movement, package creation, planning mutation, implementation, commit, or publication is authorized here.
