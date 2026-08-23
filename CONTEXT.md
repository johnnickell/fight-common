# Project Context and Ubiquitous Language

This project is a shared PHP library for applications using Hexagonal Architecture and CQRS. It supplies domain primitives, application ports, and optional infrastructure adapters without defining the business language of any one consuming application.

This file records how terms are used inside this library. Public APIs, documentation, tickets, and ADRs should use these meanings consistently.

## Architectural language

| Term | Meaning in Fight Common |
| --- | --- |
| **Domain** | Framework-free business concepts and reusable domain primitives. Domain code does not depend on Application or Adapter code. |
| **Application** | Use-case coordination and ports. It may depend on Domain contracts, PHP internals, neutral PSR contracts, and the documented `CronExpression` utility exception, but not on concrete adapters or framework implementations. |
| **Port** | An interface owned by an inward layer that describes a capability required from the outside. Examples include `CommandBus`, `UnitOfWork`, `HttpClient`, and `FileStorage`. |
| **Adapter** | An outer runtime integration that makes a third-party library, framework extension point, or infrastructure capability convenient for consumers. An Adapter often implements an inward-owned port, but it may instead translate a framework-facing contract or package external behavior behind a Fight Common API. |
| **Service container** | A registry and composition mechanism that constructs and connects application services. Fight Common's portable PSR-11 implementation belongs to Application; framework compiler passes, providers, and service factories are Adapter integrations for the same capability. |
| **Interoperability standard adapter** | A provider-neutral Adapter that translates between a Fight capability and an accepted external standard without losing observable behavior. Direct use of a standard interface needs no wrapper, and a semantic mismatch is documented rather than disguised as support. |
| **Queued Fight message delivery** | At-least-once transport of one complete Fight command or event message to a neutral handler that invokes the synchronous Fight bus or dispatcher. Post-commit submission prevents work for a rolled-back transaction but is not an atomic outbox. |
| **Framework support evidence** | The combination of library-owned adapter conformance evidence and a booted installed-package journey in the corresponding starter repository. The former proves translation behavior; the latter proves native registration and lifecycle. |
| **Capability registrar** | An Adapter class that registers one bounded set of Fight services in a concrete service container. A registrar uses explicit service and handler maps; project-owned collaborators are constructed through the container's existing factories. |
| **Service-container adapter** | Framework-facing wiring that registers one bounded Fight Common capability with a native service container. Symfony compiler passes, Laravel service providers, Yii configuration providers, and CodeIgniter service factories use different extension mechanisms but share this responsibility. |
| **Standard** | Development-time policy published under `src/Standards`. Standards are orthogonal to the runtime Domain, Application, and Adapter dependency chain; they depend only on the tooling contracts they extend, and runtime layers do not depend on them. |
| **Service** | An application-level coordinator or named registry. A service does not make an infrastructure implementation part of the domain. |
| **Dependency direction** | Runtime dependencies point inward: Adapter -> Application -> Domain. Domain uses only Domain and PHP internals. Application additionally permits neutral PSR contracts and the documented `CronExpression` utility exception. Standards remain outside that chain and cannot depend on a runtime layer. |

## Shared domain primitives

| Term | Meaning in Fight Common |
| --- | --- |
| **Value** | An immutable description or measurement compared by value rather than identity. Values validate themselves and are replaced rather than mutated. |
| **Value object** | The base implementation of `Value`, providing value equality, hashing, string conversion, and JSON representation. |
| **Identifier** | A value that identifies a domain concept and can be compared and ordered. |
| **Unique ID** | A UUID-backed `Identifier` with named generation and reconstruction methods. `MessageId` and `AuditEntryId` are examples. |
| **Specification** | A composable business rule evaluated with `isSatisfiedBy()`. Specifications combine through `and()`, `or()`, and `not()`. |
| **Collection** | A typed group of values. Lists preserve sequence, sets enforce uniqueness, tables associate typed keys and values, and sorted variants require comparison rules. |
| **Comparator** | A strategy that establishes ordering between values for sorted collections. |
| **Serializable** | An object that can describe and reconstruct its state. Serialization is representation, not persistence. |

## Messaging and CQRS

| Term | Meaning in Fight Common |
| --- | --- |
| **Payload** | The domain content carried by a message. It has an array representation and can be reconstructed from that representation. |
| **Message** | An effectively immutable technical envelope containing a `MessageId`, message type, timestamp, payload type, payload, and an isolated metadata snapshot. Standalone `Meta` values remain mutable, but a message copies metadata at its boundary and returns a copy from `meta()`. Metadata carries application, transport, audit, or observability context and is not domain state. |
| **Message identity** | A `MessageId` is the idempotency identity of one event occurrence and its payload. `withMeta()` and `mergeMeta()` derive a same-ID envelope with a different isolated metadata snapshot without creating a new occurrence or changing message equality. |
| **Metadata (`Meta`)** | Scalar, nested contextual data attached to a message without changing its payload. |
| **Command** | An imperative payload requesting a state-changing action. Commands do not return application data. |
| **Command message** | The message envelope carrying a command. |
| **Command bus** | The application port that wraps or dispatches commands to exactly one routed handler. |
| **Command handler** | The application component registered for and responsible for one command type. |
| **Command filter** | Pipeline behavior surrounding command handling, such as metrics or cross-cutting policy. |
| **Query** | A payload requesting information without expressing a state change. |
| **Query message** | The message envelope carrying a query. |
| **Query bus** | The application port that fetches or dispatches a query and returns its result. |
| **Query handler** | The application component registered for and responsible for one query type. |
| **Query filter** | Pipeline behavior surrounding query handling. |
| **Event** | A domain payload describing something that happened. It is stated in past tense and is not an instruction. |
| **Event message** | The technical message envelope carrying an event. Its timestamp records envelope creation, not necessarily the domain occurrence time; domain-significant time belongs in the event payload. A domain event and its event message are related but not interchangeable terms. |
| **Event dispatcher** | The application port that triggers or dispatches event messages to zero or more handlers. A synchronous dispatcher attempts every matching handler even when one fails, then reports the collected failures after fan-out completes. Event-specific handlers form the first priority-ordered phase; `AllEvents` handlers form a second priority-ordered phase. |
| **Event subscriber** | A component declaring the event types it observes and the handlers and priorities used for them. Idempotence is encouraged for subscribers that receive published stored events but is not required by the general subscriber contract. |

Commands use `execute()` for payloads and `dispatch()` for messages. Queries use `fetch()` for payloads and `dispatch()` for messages. Events use `trigger()` for payloads and `dispatch()` for messages.

## Persistence and validation

| Term | Meaning in Fight Common |
| --- | --- |
| **Repository** | A domain-oriented collection boundary for retrieving and persisting domain records without exposing infrastructure details. |
| **Pagination** | A request for page, page size, offset, limit, and normalized field ordering. |
| **Result set** | A typed page of records plus page and total-count metadata. |
| **Unit of work** | The application transaction boundary used to commit work and run a callable transactionally. A closed unit of work is terminally unable to accept another operation; a rolled-back transaction does not by itself mean the unit of work is closed. |
| **Required audit record** | Secret-free durable evidence required for a classified sensitive command to succeed. The protected mutation and its required audit record commit atomically; a later projection of that evidence is derived state rather than the authoritative audit record. |
| **Input data** | Untrusted field data entering validation. |
| **Validation rule** | A reusable predicate such as length, range, format, type, or required-field behavior. |
| **Validator** | A component that applies a specification to a validation context and may add field errors. |
| **Validation coordinator** | The component that runs validators and produces one `ValidationResult`. |
| **Validation result** | Either passed application data or failed error data; data and errors are mutually exclusive states. |
| **Application data** | Validated data safe for application use. |
| **Error data** | Structured field errors produced by failed validation. |

## Supporting capability language

These are distinct capabilities. Avoid using their names interchangeably.

| Capability | Canonical terms |
| --- | --- |
| **Observability** | A **health check** produces one `HealthResult`; a **health report** aggregates results and their worst status. A **metric** is a measured counter, gauge, or timing. An **audit entry** records actor, action, timestamp, and context. |
| **Authentication and security** | An **authenticator** validates a request. A **request service** signs one. A **nonce** is a one-time replay-prevention value and is consumed atomically. Password hashing and token encoding are separate security ports. |
| **Scheduler** | A **job** has a name, schedule, executable command or callable, and runtime policy. The **scheduler** determines which jobs are due and prevents overlapping execution with a lock. |
| **Process** | A **process** is an immutable execution descriptor. A **process runner** queues and executes processes with concurrency and failure behavior. A scheduled job may invoke a process, but the terms are not synonyms. |
| **File storage** | Backend-neutral file content operations, potentially local or remote, exposed through `FileStorage`. |
| **Filesystem** | Operations on the local operating-system filesystem, exposed through `Filesystem`. |
| **File transfer** | Sending, retrieving, and listing remote resources through a named `FileTransport`. A **resource** describes a remote file-system entry. |
| **Transport services** | HTTP clients, mail transports, SMS transports, and socket publishers are outbound ports with concrete adapters. A **null adapter** intentionally performs no external delivery; a **logging adapter** records and delegates or substitutes an operation as documented. |
| **JSend envelope** | A framework-neutral semantic representation of one JSend `success`, `fail`, or `error` result. It owns JSend data, message, code, and serialization but not HTTP status, headers, or a framework-native response. A controller chooses the HTTP outcome and a native response adapter converts the envelope. |
| **Registry service** | A service such as `StorageService` or `FileTransferService` that selects a named port implementation. It is not itself the storage or transport backend. |

## Access control language

| Term | Meaning in Fight AccessControl planning |
| --- | --- |
| **Invited registration** | Creation of a user identity without a password by an authorized actor. The user remains pending activation until a one-time activation grant is completed. It is distinct from public self-registration. |
| **Pending activation** | The account state of an invited user who has not completed the one-time password-setting journey. A pending user cannot authenticate or authorize application operations. |
| **Disabled user** | An active identity placed into a reversible administrative suspension. Disablement revokes every session and denies authentication and authorization; explicit enablement returns the identity to active state without restoring prior sessions. |
| **Deleted user** | A protected soft-deleted identity retained as a tombstone for stable audit and historical references. Deletion revokes every session and grant and is reversible only through an audited super-admin restoration process, never ordinary enablement. Consumer-owned retention or erasure workflows govern personal-data removal. |
| **Canonical user email** | The normalized login and delivery address uniquely reserved across pending, active, disabled, and soft-deleted users in a single-tenant project. Relationships use stable `UserId`, not email. Consumer persistence owns the unique constraint so a future multi-tenant project can scope it by tenant without rewriting identity references. |
| **Email change** | A request-and-confirm identity journey that reserves a new canonical email while the old address remains authoritative. Confirmation by a purpose-bound single-use grant changes the address, advances authentication version, and revokes every session; cancellation or expiry releases the reservation. |
| **Email-change grant** | A purpose-bound single-use aggregate authorizing confirmation of one reserved canonical email. It is not interchangeable with activation or reset, permits only one active grant per user and reservation per target address, and uses a configurable expiry with a 24-hour starter default. |
| **User restoration** | An audited super-admin recovery of a deleted user into an explicitly selected `ACTIVE` or `DISABLED` state. Active restoration retains the password for confirmed accidental deletion; disabled restoration requires the normal password-reset/reactivation path. Neither mode restores sessions or grants, and both advance authentication version. |
| **Security email template model** | The constrained, documented values AccessControl passes to a project-owned activation or password-reset template: safe display values, the one-time action URL, and expiry. It never exposes a User or grant aggregate, password hash, credential digest, or general security context. |
| **Security email delivery** | Durable invocation-neutral work for an activation or reset email. It references the user and grant, stores any recoverable raw credential only as short-lived ciphertext under a key outside the database, and destroys that ciphertext after confirmed delivery or bounded terminal expiry. The consumer decides whether its handler runs synchronously or asynchronously; the record is not the authoritative grant. |
| **Activation grant** | A hashed, expiring, single-use credential that permits one pending user to set an initial password. Reissuing an activation grant revokes its predecessor. |
| **Activation delivery** | The retryable attempt to send an invited user the current activation grant. Registration remains durably pending when delivery fails; failure is visible to the inviter, and resending revokes the predecessor grant rather than creating another user. |
| **Pending invitation correction** | An audited super-admin correction of a pending user's canonical email. It revokes the prior activation grant and delivery, releases the old address, and creates a fresh grant and delivery for the corrected address without activating the user or reusing the credential. |
| **Managed Role** | A project-defined Role with a stable UUID, authoritative name, and exact Managed Permission membership. It is readable but runtime-immutable; version-controlled definition review and reconciliation are its mutation path. Fight Common `HashSet` differences derive permissions to add and remove. |
| **Managed Permission** | A project-defined Permission with a stable UUID, authoritative uppercase-underscore name, and required `ADMIN_SAFE` or `SUPER_ADMIN_ONLY` tier. Typed authorization references and definitions must match in both directions. It is readable but runtime-immutable and is reconciled into persistence. |
| **Custom access-control record** | A Role or Permission created outside the Managed Role/Permission definitions and owned by runtime administration. Super administrators may mutate it subject to authorization and invariants; reconciliation preserves it. |
| **Managed Permission removal** | An intentional code-and-data change that first proves no consumer code path still checks the Managed Permission, then explicitly removes its Managed Role assignments and live record through reconciliation. Historical audit evidence retains the permission name without depending on the live record. |
| **Access-control deletion guard** | The invariant that rejects deletion of a custom Role or Permission while any user, role, or other live record still references it. Deletion never cascades; dependencies must be explicitly detached or reassigned first. |
| **Authenticated principal** | An immutable, framework-neutral representation of an active user for one request, containing identity, account and authentication version, and role and permission snapshots. It is not a User aggregate or framework security token. |
| **Access token** | A short-lived, purpose-bound JWT used from client memory to authenticate API requests. It is not durable session state and does not independently authorize stale account or permission data. |
| **Refresh session** | A server-tracked login on one browser or remote device. It owns an opaque refresh-credential family, revocation state, trusted persistence policy, and the authority to mint replacement access tokens. Multiple refresh sessions may coexist for one user, and authoritative state is shared across application container instances through a database or Redis adapter with atomic mutation semantics. |
| **Refresh conflict** | A bounded concurrent-refresh outcome in which a credential was just consumed by another request from the same browser session. It issues no credential, does not revoke the family, and tells the client to await the winning cookie update before one bounded retry. Reuse outside the conflict window is treated as compromise. |
| **Remember me** | A login choice that permits the current refresh session to survive browser restart and use a longer configured lifetime. It never changes access-token lifetime or grants additional authority. |
| **Current-session logout** | Revocation of only the refresh session represented by the presented credential. Other browser and device sessions remain active. |
| **Global session revocation** | Revocation of every refresh session for one user. Logout-all, disabling the account, changing the password, and resetting the password require this effect. |
| **Session management** | The capability to inspect coarse, non-secret metadata for refresh sessions and revoke one or more of them. Users manage their own sessions; a principal with `MANAGE_USER_SESSIONS` may manage another user's sessions, and every administrative revocation is audited. |
| **Required security audit** | A typed, secret-free audit record whose durable write is part of a classified sensitive command's success. It records actor, target, action, outcome, time, correlation identity, relevant session or delivery identities, and any required reason. A consumer uses one atomic UnitOfWork or atomically creates a durable audit handoff; ordinary post-commit event publication is not sufficient. |
| **Session idle timeout** | The maximum period since authoritative session activity before reauthentication is required. Rotation may advance this deadline but never the session's absolute timeout. |
| **Session absolute timeout** | The maximum lifetime of a refresh session from authentication or explicit reauthentication. Possession or rotation of a refresh credential cannot extend it. |
| **Purpose-bound token** | A signed token accepted only by validation rules for its one declared use, issuer, audience, type, algorithm, time window, subject, token ID, and key ID. API access and realtime subscription tokens are different purposes and are not interchangeable. |
| **Plain password** | A transient typed AccessControl value accepted only at password use-case and hashing boundaries. It is never persisted, serialized into events, or logged. |
| **Password hash** | The opaque typed result of password hashing stored by the User aggregate. AccessControl does not expose the implementation string through read models. |
| **Password verification** | The typed result of comparing a plain password with a hash, including validity and whether successful authentication should replace the stored hash. |
| **Session bootstrap** | The SPA loading phase that always begins without an access JWT and awaits the refresh endpoint. A valid refresh session returns a fresh access JWT and authoritative principal before authenticated UI or API work proceeds. |
| **Proactive refresh** | Refresh begun at the configured safety boundary before access-token expiry. The starter default treats a 15-minute token as needing refresh at ten minutes old, using bounded clock-offset handling and one shared awaited refresh operation. |
| **Authentication abuse control** | Configurable throttling and observation applied by both normalized account identity and source signal, with generic public outcomes and no attacker-triggerable permanent account lockout. CAPTCHA and external risk scoring are replaceable consumer integrations. |
| **Realtime topic authorization** | The server-side decision that maps an authenticated principal and requested realtime topics or channels to the exact private subscriptions that principal may receive. Client display state and possession of a topic name are not authority. |
| **Private realtime update** | A published update deliverable only to a subscriber whose short-lived subscription credential authorizes its exact topic or channel. Publication and subscription credentials are separate from ordinary API access tokens. |
| **Public realtime envelope** | The versioned browser-facing representation of one explicitly publishable domain event. It carries a stable public name, message identity, occurrence time, authorized topic, an allowlisted payload, and allowlisted technical metadata without exposing the internal PHP message serialization. |
| **Realtime event transformer** | One Application component dedicated to one publishable domain event that maps it to a public realtime envelope. Transformers contain no provider transport logic and are composed as a registry without a central event-type switch. |
| **Public realtime update** | An explicitly designated update whose underlying information is already available without authentication. Topic identifiers are discoverable rather than secret, so public delivery is never appropriate for user, account, job, administration, or personalized data. |
| **Realtime revocation window** | The bounded period during which an already-open provider connection may remain after its backing application session is revoked. Revocation prevents credential renewal immediately; provider-specific disconnection may shorten the portable maximum. |

## Engineering quality language

| Term | Meaning in Fight Common |
| --- | --- |
| **Coding standard** | The reusable, opinionated set of PHP formatting, documentation, and declaration-layout rules published by Fight Common. A consuming project chooses the files to which the standard applies. |
| **Public API manifest** | The machine-readable inventory of PHP declarations for which Fight Common makes a versioned compatibility promise. Its initial baseline contains every production-autoloaded declaration in the authoritative published `1.1.0` source unless that declaration was already marked `@internal`; every later production declaration must be deliberately classified. For each public declaration, callable, constructible, extensible, and implementable are independent consumer-operation promises. A non-final class is extensible only when affirmative design or history evidence establishes that promise. |
| **Behavioral contract** | An explicit consumer-facing behavior promised by published documentation, public PHPDoc, or an accepted ADR. A stable contract ID links the normative statement to designated compatibility evidence. Ordinary tests and incidental observed behavior do not create behavioral contracts by themselves. |
| **Compatibility contract** | The combined versioned promises covering the public API manifest, behavioral contracts, exceptions, serialization, persisted data, framework adapters, accepted runtime environments, Composer package metadata, and the coding standard. Compatibility is classified by consumer-visible effect rather than PHP signatures alone. |
| **Deprecation** | A minor-release notice that a still-functional public contract is planned for removal or incompatible change in a future major release. A deprecated contract remains supported for at least one released minor before removal. |
| **Supported release line** | A `major.minor` maintenance line whose phase, exact branch, release baselines, allowed fix classes, and exclusive UTC support boundary are declared in the canonical support-policy data. Only its latest patch release is supported. |
| **Release baseline** | The canonical release tag and immutable peeled commit OID against which a candidate's compatibility is certified. Baselines are selected by release class and must have valid ancestry; tooling never guesses from tag ordering. |
| **Affected line** | A supported release line on which focused public behavioral evidence reproduces a defect. An introducing commit may be unknown, but affected status may not be inferred from ancestry alone. |
| **Compatibility exception** | Exact, human-approved authority for one otherwise incompatible patch candidate addressing a security, imminent data-loss, or critical interoperability failure when no compatible repair exists. It is bound to the candidate, version, baselines, evidence, and overridden findings and is never reusable. |
| **Compatibility manifest** | The committed `compatibility/manifest.json` authority that classifies public declarations and consumer operations, links behavioral contract IDs to normative documentation and fixtures, records package-surface promises, and identifies its authoritative baseline. Generated inventories validate completeness but do not replace intentional classification. |
| **Compatibility finding** | A stable, machine-reported difference between a release baseline and candidate. An indeterminate finding blocks certification until evidence classifies it or an exact compatibility exception authorizes it. |
| **Quality gate** | A mandatory automated check that must pass before work is accepted. A report without an enforced pass/fail condition is not a gate. |
| **User acceptance testing (UAT)** | A bounded human review of a user-facing journey after its automated checks pass. A project may adopt UAT as a required pre-completion gate; doing so supplements automated evidence and does not replace it. |
| **Architecture gate** | The quality gate that verifies inward dependency direction between Domain, Application, and Adapter. |
| **Coverage gate** | The quality gate that compares covered statements with all executable statements. Fight Common requires exact complete statement coverage rather than a rounded percentage. |
| **Dependency freshness lane** | CI verification performed after resolving the latest versions permitted by the package constraints. It is distinct from a local build using already-resolved dependencies. |
| **Build** | The complete ordered set of quality gates used to decide whether the repository is acceptable. |
| **Release skill** | A guided operator workflow that owns one release phase. It may inspect release state and invoke the deterministic commands allowed for that phase, but it does not own release policy or cross into another phase's effect class. |
| **Phase handoff** | The durable, content-addressed output of one release phase. It binds the phase's inputs, verified postconditions, and resumable next action for explicit acceptance by the next release skill. |
| **Planning control artifact** | An immutable plan, run-state projection, machine result, or bounded log written under `.runs/` for release coordination. It is operational bookkeeping, not a mutation of tracked repository content, dependencies, Git refs, or external release state. |
| **Version authorization** | The human approval of one exact release version after deterministic inspection recommends the minimum valid SemVer increment. The authorized version is a bound plan input; changing it requires a new immutable plan. |
| **Source commit** | The immutable commit OID inspected and bound by a release plan before packaging. A source branch is descriptive context only and may not substitute for the OID. |
| **Support-policy binding** | The digest or immutable identity of the support-policy data used by a release plan, including supported-line status and boundaries. A changed policy requires a new plan. |
| **Candidate commit** | The immutable commit OID produced by packaging and carried by its phase handoff. It is distinct from the plan's source commit and is required for certification and publication authorization. |
| **Packaging effect set** | The explicit, dry-run manifest of local branch, file, dependency-metadata, and candidate-artifact effects approved for one packaging run. Packaging may not perform effects outside this set. |
| **Certification stop handoff** | The durable phase handoff emitted when certification fails or cannot classify evidence. It records the stop state, evidence, and resumable next action without changing the candidate or external state. |
| **Publication authorization** | A human approval for one bounded external release effect, bound to the exact plan, candidate, version, baselines, evidence manifest, and exceptions. It is not reusable for a different effect or changed input. |
| **Partial publication** | The persistent stop state entered when an external effect may have occurred but its postcondition is uncertain. Resumption requires operator-directed reconciliation and fresh verification. |
| **Handoff record** | The machine-readable phase handoff fields: `plan_id`, `run_id`, phase and status, bound object IDs and digests, approvals, evidence references, any stop state, and one resumable next action. |
| **Certification manifest** | The compact, immutable digest-bearing record that composes every required certification lane. A single hosted check or raw log cannot substitute for it. |
| **Capability boundary** | The closed set of actions a release skill may perform in its phase. An action outside that set is rejected even when an underlying command could technically perform it. |
| **Reviewed fix** | An immutable, already-reviewed change set identified by exact commit OIDs and its merged pull request provenance: base and head OIDs, approvals, required-check conclusions, and merge receipt. A branch or pull request number alone is not a fix identity. |
| **Affected-line patch** | A patch workflow execution for one supported release line, based on that line's exact current tip and independently classified and certified for compatibility. EOL lines are read-only. |
| **Forward port** | A separately reviewed and certified application of an older affected-line fix to a newer affected supported line, ordered oldest to newest and carrying predecessor and source provenance. |
| **Urgent release mode** | Guided metadata and operator ergonomics for a time-sensitive patch. It collects the ordinary evidence and approvals into one action packet; it does not bypass review, compatibility, certification, or authorization. |

## Framework support language

| Term | Meaning in Fight Common |
| --- | --- |
| **Supported framework line** | A stable major line of a consumer framework for which Fight Common promises a tested, documented composition. The range is expressed as an exact Composer constraint and is deliberate Fight Common policy, not the framework's own support policy. |
| **Current-only support window** | The default Fight Common posture: only the current stable major line of each framework is supported. The window never widens beyond current plus the immediately-preceding major. |
| **Widen trigger** | The event that moves a supported range from current-only to current + previous: a framework shipping its next major version. The widened form is the union `^new || ^current`. |
| **Tighten trigger** | The event that drops the oldest line from a widened window: the framework stopping maintenance of that line. A line is never silently carried past the framework's own maintenance boundary. |
| **Default composition** | The one opinionated, documented, Composer-installable set of native, portable, and third-party facilities Fight Common recommends for a capability in a starter. Replaceable alternatives are composition-root choices, not obligations to test every combination. |
| **Recommended composition** | A default composition that fills a capability with no first-party native facility in its framework. There is no unsupported capability: every public contract has a recommended, documented solution. |
| **Starter-owned wiring** | Integration code that lives in the starter's composition root rather than in a shared Fight Common package. Examples include a Laravel ServiceProvider, a Yii configuration provider, a CodeIgniter `Config\Services`, a Slim explicit container, and Symfony project-owned service loading. |
| **Capability worksheet** | The per-framework record, for every public Fight Common contract, of the native facility, existing reusable binding, starter-owned wiring, new-shared-adapter decision, functional journey, and remaining unknown. |
| **Isolated fixture** | A minimal Composer verification root requiring the Fight Common candidate plus exactly one framework's constraint set, resolved at lowest and latest as evidence. It is a dependency manifest and compatibility probe, not a starter application. |
| **Combined resolution lane** | The root dependency set in which all supported frameworks and optional adapter packages must resolve together. Each widened previous line is proven in its own isolated fixture before the combined lane is re-resolved. |
| **Forward-compatible seam** | A stable Fight Common port or boundary chosen so a future native facility (for example a released `yiisoft/queue`) can replace the current composition without breaking the public contract. |

## Proposed Event Sourcing language for 1.2

These terms describe the planned optional extension. They do not describe existing production APIs until their tickets are implemented.

| Term | Planned meaning |
| --- | --- |
| **Event-sourced aggregate** | The framework-free lifecycle contract used by repositories for an aggregate whose state changes only by explicitly applying domain events. It owns a static named constructor for reconstitution from history and exposes its identity through the existing `Identifier` contract. |
| **Aggregate root** | The reference abstract implementation of the event-sourced aggregate contract. It stores the consumer's domain-specific `Identifier`, implements `id()`, and owns version tracking and pending-event lifecycle while the consumer aggregate owns explicit event routing and semantic state transitions. |
| **Recorded event** | A new event held by an aggregate until it is persisted. |
| **Released event** | A recorded event returned in recording order and immediately removed from the aggregate's pending collection for persistence. |
| **Stored event** | An append-only storage envelope containing an event message, stream version, stable event name, persisted schema version, and global position. Its stored identity is the stable event name, not the event payload's PHP class name. After upcasting, the message contains the current payload while the envelope retains the schema version actually persisted. Publication may derive a same-ID message with additional technical metadata without rewriting the stored snapshot. Global position provides prefix-stable commit visibility: once a position is visible, no lower-positioned event may become visible later. |
| **Aggregate definition** | Repository configuration pairing one stable aggregate name with the current PHP aggregate class. It maps durable stream identity without requiring a global aggregate registry. |
| **Stream** | The ordered event history of one aggregate, identified by a stable aggregate name and aggregate identifier rather than by the PHP aggregate class name. |
| **Expected version** | The stream version observed before new events were recorded and checked during append for optimistic concurrency. An empty stream has version zero and its first event has stream version one. |
| **Event Store** | The append-only port that accepts event messages, maps them to durable event identity and schema, writes versioned streams, and returns hydrated stored events from stream-ordered and globally ordered reads. |
| **Event mapper** | The explicit, bidirectional registry for one Event Store that maps a stable event name to the current PHP event class and reconstructs an event message from stored data. It must contain every event persisted in that store, and aliases must be unique across the store. Consumer configuration registers every stored event; attribute scanning, convention-based discovery, and FQCN fallback are not part of the contract. A PHP class rename changes the mapping without changing stored history. |
| **Event mapping provider** | A portable declaration of typed event mappings owned by one bounded context. It declares a durable event namespace plus locally unique event names; each typed mapping owns the current PHP class, current schema version, and complete upcaster chain. The event mapper qualifies local names into canonical stored names. Multiple providers may compose the complete catalog for one Event Store and may be registered directly or collected by optional framework integration. |
| **Upcaster** | A one-event-to-one-event in-memory transformation from one stored payload schema version to the next integer version. It does not rewrite stored history, and class renames alone do not require upcasting. |
| **Projector** | A stably named idempotent consumer that declares the current event payload FQCNs it understands and moves matching, already-upcasted stored events into a read model. Its stable name owns checkpoint identity independently of its PHP class. Projectors do not know stable storage aliases or legacy schemas. |
| **Projection runner** | The worker-facing service that polls stored events for one projector, handles them in global order, and advances that projector's checkpoint only after each successful projection. |
| **Read model** | Query-oriented data derived from events; it is not the authoritative event history. |
| **Checkpoint** | The last global event position successfully processed by one named projector. Processing means either successfully projecting a declared current event type or successfully skipping an undeclared type. The runner advances it per event, not only after a complete fetched batch. |
| **Event publication** | Post-commit fan-out of stored event messages through the Event Dispatcher in a worker process separate from projection. Publication attempts all matching subscribers and does not automatically replay an event because a subscriber failed. |
| **Publication cursor** | The last global position for which event publication attempted all matching subscribers. It advances after the complete attempt even when subscriber failures were collected; unlike a projection checkpoint, it records attempted fan-out rather than successful state projection. |
| **Event publication runner** | A stably named worker-facing service that polls committed stored events, dispatches their original event messages through a `SynchronousEventDispatcher`, reports collected subscriber failures, and advances its publication cursor after each complete attempt. Multiple named runners may publish independently from one Event Store. |
| **Publication cursor store** | The port that loads and monotonically saves attempted-fan-out positions independently by stable publication name. It has no reset operation in 1.2 and is distinct from a projector checkpoint store even when adapters share persistence mechanics. |
| **Event dispatch failure** | The aggregate exception thrown by a Synchronous Event Dispatcher only after it has attempted every matching handler and one or more failed. It contains the ordered handler failures and proves fan-out completed despite those failures. |
| **Event handler failure** | A transient value pairing one failed callable description with its original `Throwable`. It allows callers such as the Event Publication Runner to distinguish completed fan-out with handler failures from an unexpected dispatcher or infrastructure failure. |
| **Event publication failure** | A structured snapshot of one stored-event publication attempt that had handler failures. It includes publication, stored-event, and message identity, the UTC time at which dispatch began, and each failed callable's description, exception FQCN, integer code, and bounded diagnostic message. It excludes original throwables, stack traces, event payloads, and message metadata. |
| **Publication failure recorder** | The port used by the Event Publication Runner to record one aggregated `EventPublicationFailure` before advancing its publication cursor. Fight Common 1.2 provides in-memory and DBAL implementations plus a PSR-3 logging decorator that requires and delegates to another implementation. |

Event Sourcing remains additive to CQRS. Aggregates record plain `Event` payloads; repositories create messages and storage envelopes. Projectors consume stored events independently using at-least-once delivery.

`EventMessage` remains the live messaging contract and may identify its payload by PHP class. Durable Event Store adapters persist the stable event name and schema version instead of treating the serialized `EventMessage` payload type as the stored event identity. Only events persisted by the Event Store require mappings; events used solely by the Event Dispatcher do not.

An event namespace is durable storage identity rather than a display name or PHP namespace. For example, an `orders` provider may declare the local name `order-placed`, producing the canonical stored name `orders.order-placed`. Renaming a bounded context in code does not by itself rename its stored event namespace.

Schema versions begin at one. Each event mapping owns and validates an unbroken upcaster chain from version one to its declared current schema version; version-one mappings have no upcasters. Missing, duplicate, or skipping steps make registration invalid.

Stored-event mapping is fail-closed. Reading an unknown stable event name, a schema newer than the registered current version, or an older schema without a complete upcast path raises a dedicated mapping failure. Readers never downcast, skip, or best-effort hydrate unsupported history.

Event Store append is idempotent by message identity. If every requested `MessageId` already occupies the intended stream positions immediately after the supplied expected version, the operation succeeds as already appended without writing again or comparing payload or metadata content. A partial batch match or any message ID in a different stream or position fails closed.

Event Store implementations own event mapping on both sides of persistence. Append accepts `EventMessage` instances and snapshots their current metadata while mapping payload classes to stable event names and current schemas. Reads upcast payloads, hydrate current event classes and messages, and return `StoredEvent` envelopes. Aggregate repositories do not handle aliases, schemas, or upcasters.

`Meta` is a mutable technical-context value, but messages isolate it: construction stores a copy, `meta()` returns a copy, and `withMeta()` or `mergeMeta()` creates a same-ID envelope with a new snapshot. The single append persists one metadata snapshot; later derived publication timing or transport context does not update Event Store history or affect domain reconstitution.

The Event Store preserves the EventMessage creation timestamp as technical envelope time, normalized to UTC with microsecond precision. It does not infer domain occurrence time from that value, and ordering remains defined only by stream and global positions.

Fight Common 1.2 does not introduce an EventMessage factory or clock abstraction. Aggregate repositories create messages through the existing `EventMessage::create()` contract; callers and tests that require exact IDs, timestamps, or metadata may use the public constructor.

Global ordering must be safe for checkpoint polling, not merely unique and increasing after all transactions finish. Durable stores serialize global-position allocation inside the append transaction so a reader that checkpoints a visible position cannot later miss a lower position committed out of order. An auto-increment event-table key alone does not provide this guarantee on MySQL.

Projection batches bound polling work but do not define checkpoint atomicity. The runner handles and checkpoints each event in global order so a failure resumes from the failed event rather than replaying the successfully handled prefix of its fetched batch.

Projectors declare current event payload FQCNs, following the same code-facing convention as Event Subscribers. The Event Store has already resolved stable aliases, applied upcasters, and hydrated current payloads before projection routing. The runner invokes a projector only for declared FQCNs and advances its checkpoint over undeclared types as successful skips. Adding a handled type later requires a checkpoint reset and rebuild to process its history.

Each projector declares an explicit stable name, such as `orders.order-summary`, used as its checkpoint key. Renaming or moving the PHP projector class does not rename or reset that durable processing identity.

Normal checkpoint saves are monotonic and reject backward movement. `reset(projectorName)` is a distinct administrative operation that returns one projector to position zero. Consumers stop its worker and clear or recreate the read model before reset; Fight Common does not coordinate read-model replacement. Arbitrary backward checkpoint positions are outside 1.2.

A projector failure stops the current batch immediately, leaves the failed event uncheckpointed, and propagates to the caller. The core runner never processes a later global position after an earlier one fails; consuming workers own retry, backoff, and dead-letter policy.

`ProjectionRunner` and `EventPublicationRunner` execute in separate worker processes with different guarantees. Projection is ordered at-least-once processing: successful idempotent state updates advance a projector checkpoint, while failures stop and retry. Event publication considers only committed stored events, attempts every matching subscriber, records one aggregated `EventPublicationFailure` through a `PublicationFailureRecorder` when any subscribers fail, and advances its position through a distinct `PublicationCursorStore` after the complete attempt without automatically redispatching that event. A crash before cursor advancement can still cause duplicate delivery, so subscriber idempotence remains advisable.

Fight Common 1.2 records publication failures but does not define automatic or targeted replay. A consuming project may implement replay, including invoking only subscribers that originally failed, and a proven implementation may inform a later shared contract.

Each subscriber failure identifies the invoked handler by its current subscriber FQCN and method. Symfony's default service registration uses the subscriber FQCN as its service ID, so a consuming project can resolve and invoke that handler again while the class and method remain unchanged. This is operational identity rather than durable storage identity: subscriber refactors may require project-owned migration or interpretation of old failure records.

The Event Dispatcher continues to support handlers registered directly as arbitrary callables. Named functions are described by function name and closures by a non-replayable `Closure` descriptor. Those fallbacks preserve dispatcher compatibility, but only a class-and-method description is an intended candidate for project-owned targeted invocation.

`SynchronousEventDispatcher::dispatch()` retains its `void` return contract and existing two-phase ordering. It first attempts event-specific handlers by priority, then attempts `AllEvents` handlers by priority. Each handler invocation catches any `Throwable`, including PHP `Error` values such as `TypeError`, and continues within and across both phases. Afterward it throws one `EventDispatchFailed` aggregate containing ordered `EventHandlerFailure` values with their original throwables. The aggregate is thrown only after both phases complete. Failures while resolving or preparing handlers remain dispatcher infrastructure failures and do not prove fan-out completed.

The Event Publication Runner catches `EventDispatchFailed`, converts it into one `EventPublicationFailure`, records it, and may then advance the publication cursor. Any other dispatcher exception propagates without cursor advancement because the runner cannot prove that every handler was attempted.

At the beginning of each stored-event dispatch attempt, the runner captures the current time directly with `DateTimeImmutable`, normalizes it to UTC with microsecond precision, and reuses that value if it creates an `EventPublicationFailure`. Fight Common does not introduce a clock abstraction solely for this operational timestamp. Comparing it with the EventMessage creation timestamp can estimate storage-to-publication latency without treating either timestamp as domain occurrence time.

Conversion snapshots each handler failure as its callable description, exception FQCN, integer exception code, and bounded diagnostic message. The message is normalized to valid UTF-8, stripped of unsafe control characters, and limited to 4 KiB. Fight Common does not attempt secret detection or redaction; consuming projects treat exception messages as user-surfaceable text and do not place credentials or sensitive payload data in them. The publication failure does not retain the original `Throwable`, stack trace, event payload, or message metadata. Both DBAL persistence and the PSR-3 logging decorator consume this same portable snapshot; full throwable diagnostics remain transient on `EventDispatchFailed`.

The DBAL publication-failure recorder durably stores one aggregated record per publication name and global position, including message and event identity, attempt time, and the bounded handler-failure snapshots. Its purpose in 1.2 is operational evidence; failure queries and replay APIs remain deferred.

The PSR-3 publication-failure recorder is a decorator rather than an independent persistence choice. It requires another recorder, logs an `EventPublicationFailure` first, and then delegates the same record to that recorder, such as the in-memory or DBAL adapter. Either logging or delegation failure propagates and prevents publication-cursor advancement. A failed delegate may therefore produce a duplicate log on retry; publication name and global position provide the correlation key. This composes observability with the desired storage behavior and prevents logging from silently replacing recording.

Subscriber failures do not block publication-cursor advancement after their aggregated publication failure is successfully recorded. Failure-recorder or cursor-store infrastructure errors do block advancement and propagate, causing the publication attempt to retry. Failure recorders are idempotent by publication name and global position so partial recording or a crash does not create duplicate operational records.

Each Event Publication Runner has an explicitly configured stable publication name. Publication cursors and failure records are scoped by that name, allowing independent subscriber pipelines to consume the same Event Store without sharing progress.

The Event Publication Runner requires `SynchronousEventDispatcher` because its contract observes completion and collected failures from every matching subscriber. An asynchronous dispatcher acknowledges transport submission rather than subscriber execution and is outside the publication-runner boundary.

Publication cursor saves are monotonic. `PublicationCursorStore` has no reset operation in 1.2 because resetting would redispatch stored history to subscribers that are not required to be idempotent. Whole-stream and targeted republication belong to the deferred replay workflow.

Releasing events is a fail-stop boundary. If a repository save fails after release, that aggregate instance is invalid and must be discarded; retry begins by loading a fresh aggregate. Event Store implementations may perform safe transient retries internally while they retain the released events.

Aggregate version is the number of events applied to its current state, including newly recorded events. Replay increments version without recording, and recording both applies the event and increments version. When releasing a batch, its expected version is the aggregate version minus the number of released events.

Aggregate event application is fail-closed. Recording or replaying an event that the aggregate does not explicitly route raises a domain failure. An intentionally state-neutral historical event is still routed explicitly to a no-op transition.

Reconstitution belongs to the aggregate rather than an infrastructure factory. A generic repository receives the aggregate class, loads its history, and invokes the aggregate's static reconstitution contract; the aggregate retains control of its constructor and initialization rules.

Aggregate reconstitution consumes ordered plain `Event` payloads. The repository unwraps stored envelopes and live messages before invoking the aggregate, keeping storage aliases, schema versions, positions, message identity, and metadata outside aggregate state. Domain-significant values belong in the event payload.

Generic event-sourced repository lookup uses nullable `find(Identifier)`. An empty Event Store stream becomes `null`, and reconstitution is not invoked. Application services and handlers decide whether absence is exceptional and choose the use-case-specific failure.

Each generic aggregate repository receives an `AggregateDefinition` for its one aggregate type. The definition supplies the stable aggregate name used to construct stream identity and the current PHP class used for reconstitution. A class refactor updates the definition without changing stored stream names.

An event-sourced aggregate identifier implements `Identifier`. Repositories convert it to its stable string representation when constructing a stream identity; the stable aggregate name distinguishes identical identifier strings belonging to different aggregate types.

## Language rules

- Prefer the exact public contract name when discussing a library capability.
- Distinguish a payload from its message envelope.
- Distinguish domain rules (`Specification`) from input validation rules.
- Distinguish persistence (`Repository`, `UnitOfWork`) from serialization.
- Distinguish File Storage, Filesystem, and File Transfer.
- Consumer applications own their business-specific aggregate, command, query, event, and read-model names.

## Release coordination language

| Term | Meaning in Fight Common |
| --- | --- |
| **Release signer** | The one approved OpenPGP identity authorized to sign new release tags. Its documented fingerprint is the trust root for publication verification; private key custody remains with an operator or hardware-backed signer and never with CI. |
| **Signed release tag** | A signed annotated Git tag whose tag object, verified OpenPGP identity, tag name, and peeled candidate commit are all checked before publication. New releases require this form; legacy lightweight tags are historical and are not rewritten. |
| **Signer rotation** | A change, loss, or revocation of the release signer pauses publication until a new fingerprint set is explicitly approved in a new release plan. Historical signatures remain verified against their recorded fingerprint. |
| **Immutable publication** | A GitHub release publication permitted only after immutable-release capability and protected approval are verified, with the exact signed tag, commit, and approved assets rechecked. A mutable release is not equivalent provenance. |
| **Protected publication checkpoint** | The GitHub `release-publication` environment must require a named human approval before an immutable release is made public. Preparing a draft is allowed before approval; publishing without the checkpoint or falling back to a mutable release is not. |
| **Single-operator publication** | Fight Common permits one named operator to create and verify the signed tag and approve the protected GitHub publication when no second operator exists. The dual role is recorded in the evidence manifest rather than represented as independent approval. |
| **Downstream projection** | Packagist's Composer metadata and distribution archive view of the GitHub release. It is observed and verified after GitHub publication but never becomes release authority. |
| **Incomplete publication** | A fail-closed state entered when Packagist propagation, metadata, installation, or external-effect verification cannot complete. Recovery is explicit and remains bound to the original plan and evidence. |
| **Clean-install proof** | A bounded receipt from a temporary Composer consumer that resolves the exact published version through Packagist, verifies source and distribution metadata, and passes the approved public smoke behavior. |
| **Packagist observation window** | The bounded downstream verification period: poll at 15 seconds, 30 seconds, 1 minute, 2 minutes, then every 5 minutes for at most 30 minutes. Timeout produces `packagist_incomplete`. |
| **Release archive** | The deterministic rootless ZIP named `fight-common-vX.Y.Z.zip`, built with committed Composer exclusions and normalized timestamps and ordering. Its SHA-256 is recorded in the evidence manifest. |
| **Clean-install receipt** | Evidence from `composer install --prefer-dist --no-dev` in a temporary consumer, including production autoload verification and a representative public-API probe. The manifest and receipt are permanent release assets; detailed logs are retained for 90 days. |
| **Release plan** | An immutable, content-addressed description of one intended release operation. Its `plan_id` is the SHA-256 digest of its canonical JSON representation and binds the candidate, baselines, policy inputs, expected effects, evidence requirements, version, and approvals. |
| **Release run** | One execution attempt associated with one release plan. A run has its own unique `run_id`, append-only transition evidence, bounded logs, receipts, and current-state projection; retry creates a new run and resume continues the named run only after revalidation. |
| **Evidence manifest** | The compact, versioned, immutable machine authority for release inputs, checks, package digests, Git identities, external receipts, and expected downstream projections. Its canonical JSON has a SHA-256 `manifest_id` that authorization may bind. Detailed logs support the manifest but do not replace it. |
| **Partial publication** | A fail-closed release state entered after an external effect occurs but the intended verified publication is incomplete or uncertain. Automatic deletion, force-push, tag reuse, version substitution, and blind retry are not valid recovery actions. |
| **Release command** | The repository-owned `bin/release` executable and its narrow subcommands. It computes policy and evidence deterministically; human or hosted controls retain consequential authorization. |
| **Postcondition-driven resume** | Re-entry behavior that re-resolves every bound input and re-verifies every completed postcondition before advancing, rather than trusting a prior state label or exit code. |
| **Machine result** | A versioned JSON result emitted by every release-command invocation. Human-readable output renders this result; stable coarse exit codes classify the outcome and detailed finding IDs carry the explanation. |
| **Release state** | The current verified position of one release run. Progress states describe completed work; stop states describe drift, failed checks, missing authority, conflicts, external uncertainty, supersession, or an expired support boundary. Every state exposes the next permitted operation or required human action. |
| **Operator journey** | One supported release-operation path from routing through its bounded effects, verification, and handoff or stop. |
| **Journey card** | The runbook section that defines one operator journey's routing, inputs, evidence, approvals, commands, postconditions, stop handling, and next action. |
| **Routing decision** | A deterministic classification of repository state, release state, change intent, affected lines, urgency metadata, and release class that selects the correct operator journey. |
| **Stop handoff** | Durable evidence of a blocked, failed, uncertain, cancelled, or otherwise stopped operation together with its owning escalation and exactly one resumable next action. |
| **Effect authorization** | Explicit human approval for one bounded local or external mutation identified by the release plan and its immutable inputs. |

## Durable and ephemeral work

Committed planning state lives in `planning/`. Coordinate-build artifacts live under `.runs/<date>-<slug>/`; `.runs/` is gitignored and is never canonical project history. Important results and deviations from a run must be copied back to its ticket.

## Decisions

Architectural decisions live in `planning/adr/`. Public Event Sourcing integration documentation lives in `docs/event-sourcing.md`.
