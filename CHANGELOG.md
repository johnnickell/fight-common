# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Organize the new execution, Notice, Task and subscription APIs beneath `Domain\Mcp`, `Application\Mcp` and
  `Adapter\Mcp`. Application homes follow their aggregate; concrete execution inspection/Notice recovery Tools,
  schemas and public-safe mapping live in `Adapter\Mcp\Tool`, dispatching through the shared QueryBus. DBAL
  persistence/read adapters use `Adapter\Mcp\Persistence\{Aggregate}\Dbal`, with genuinely shared execution support
  separate. These APIs have never shipped in a tag: final composition replaces development-only Store/Lifecycle,
  RecordingTransaction and receipt wrappers directly, without snapshot aliases or data migrations. Task handlers
  own operation-specific use cases; repositories, transaction boundaries, committed/recovery readers and Application
  disclosure projection have distinct owners. See the [namespace map](docs/mcp-namespaces.md).
- Give new Task/execution/Notice values explicit ValueObject equality, hashing, reconstruction and deliberate
  structured JSON. Role-specific SessionId, PhaseId, NoticeId, producer/UUID ObservationIdentity and exact
  32-lowercase-hex TaskId retain their separate trusted scope and explicit string wire/storage boundaries.
  Task definition/state/input values preserve finite floats and nested object/list/null distinctions independently
  of ambient serialization precision, including byte-limit reconstruction checks. Recorded roots retain time;
  RecordedNotice additionally retains its substantive commit position. See [Task classification](docs/durable-tasks.md#model-classification-and-value-contracts)
  and [execution/Notice classification](docs/maintenance/evidence-domain-values.md).
- Legacy AI-operation/webhook APIs gain equally deprecated `Mcp\Operation` homes while released `Auth` names
  remain functional with their **original v1.2.0 deprecation** and v2.0.0 target. No retired AI Tool is revived.
  Generic protocol semantics, routes, authorization, stateless forms and signing remain independently composed.

### Deprecated

- The 17 relocated MCP foundation root spellings, in documentation only. Prefer `Application\Mcp\Capability`,
  `Protocol` (including `Protocol\Exception`), `Http` and `Tool`; `McpResponder` and `McpDiagnostics` remain at root
  without deprecation. Canonical aliases preserve released runtime identity, finality, named construction,
  consumer type compatibility, catches, reflection and saved values. First deprecation is **planned to ship in
  v1.5.0, not yet released**; removal needs separate future-major authorization after an actually released minor. See the
  [complete foundation migration map](docs/maintenance/mcp-foundation-namespaces.md).
- The nine released MCP HTTP adapter names under `Adapter\Http\Mcp`, `Adapter\Http\Symfony` and
  `Adapter\Http\CodeIgniter`. Use their canonical `Adapter\Mcp\Http`, `Adapter\Mcp\Symfony` and
  `Adapter\Mcp\CodeIgniter` names instead. Old public names remain functional throughout 1.x without runtime
  notices. First deprecation is **planned to ship in v1.5.0, not yet released**; removal requires a separately
  authorized future major after at least one actually released minor of deprecation.
  See the [complete adapter migration map](docs/components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility).
- Direct reads of 50 public readonly properties across 14 released HTTP/OAuth/MCP classes, in documentation only.
  Use the matching typed property-name getters instead. Storage, construction, named arguments, validation,
  serialization and wire/security behavior remain unchanged; no runtime notices are emitted. First deprecation
  is **planned to ship in v1.5.0, not yet released**. Retain at least one actually released minor before separately
  authorized future-major removal; Standards is entirely excluded. See the [complete migration map](docs/maintenance/public-property-getters.md).
- Four default-only schema `TABLES` constants for Execution, Task, Notice and Subscription, with first deprecation
  **planned to ship in v1.5.0, not yet released**, targeting separately authorized v2.0.0 removal after that window.
  Read actual configured names from the existing selection objects; values/order/visibility remain unchanged.
  See the [constant migration map](docs/maintenance/table-name-constants.md).

The [consolidated deprecation inventory](docs/maintenance/deprecations.md) preserves earlier v1.2.0/v1.4.0 dates,
replacements and alias/serializer/container/queue/storage risks. ResultSet legacy construction is **not deprecated**.
No API removal, mandatory v1.6.0, platform/coding-standard tightening or final-1.x support retirement is authorized.

### Added

- Opt-in bounded `prompts/list` and independent `prompts/get`, with fresh availability, declared string arguments,
  lazy selected providers, complete text/Resource-link messages, authenticated visible-catalog pagination and final
  encoded-response limits. No content execution, link fetch or permission-by-listing is implied. See [Prompts](docs/mcp-prompts.md).
- Durable execution Session/Phase/Observation facts, phase activity and measured usage, with trusted admission,
  retained-content retry identity, atomic DBAL recording and safe reference-only post-commit events. Protected
  Detail bodies are stored separately; independent authorized reads/removal preserve safe evidence and prevent
  retry resurrection while identity remains. Authorized summary/list/get/phase/activity Queries distinguish
  unknown/partial measurement and live pages from snapshots or Workflow authority. See [recording](docs/execution-observations.md),
  [inspection](docs/execution-reads.md), [protected reads](docs/execution-protected-reads.md) and [removal](docs/execution-detail-removal.md).
- Durable safe Notice recording, independently admitted inspection/removal, and confidential authenticated
  continuation recovery with explicit complete/incomplete/uncertain outcomes. The optional `notices.recover` Tool
  uses shared Queries; namespace/loss evidence never becomes a public global counter. Optional public/private
  Mercure publication separately reauthorizes the current whole audience after commit, with safe failure statuses,
  not an outbox or delivery/retry guarantee. See [Notices](docs/safe-notices.md), [inspection](docs/notice-inspection.md),
  [recovery](docs/notice-recovery.md) and [publication](docs/notice-publication.md).
- Guarded request-scoped Tool/Resource subscriptions and optionally Prompt catalog changes, with current signal
  admission, owner-bound finite tracking, bounded continuity and callback-time cancellation/deadline/freshness
  checks. Independently authorized subscriber summaries/pages distinguish registrations, subscriber identities
  and recently observed/disconnected/stale-uncertain evidence, not actual connectivity. See [subscriptions](docs/mcp-subscriptions.md)
  and [subscriber inspection](docs/subscriber-inspection.md).
- Durable asynchronous Task lifecycle, retrieval and whole-row removal, with independent admissions, TTL and
  retained-lifetime retry/version fences. Protected requested input has atomic partial-batch validation and
  single-use continuation; cancellation records cooperative intent, not a stopped worker or rollback. Explicit
  MCP Tasks composition adds negotiated start/get/update/cancel and bounded outputs. Guarded live/reconnect
  delivery rereads authorized whole state and uses visibility/lifetime-bound revisions plus a consumer-supplied
  synchronized byte-handoff guard; it neither replays history nor grants a stream-long permission lease.
  See [Tasks](docs/durable-tasks.md), [input](docs/task-input.md), [cancellation](docs/task-cancellation.md),
  [MCP serving](docs/mcp-tasks.md) and [live state](docs/mcp-task-live.md). Workers and consumer policy remain external.
- Explicit physical-name configuration across all nineteen DBAL tables, including Event Store sequences,
  checkpoints/publication, execution state/bodies, Task/Notice and subscriptions. Share selections across schema,
  runtime, readers, removal and independent processes; omitted names keep exact defaults. Two ORM-mapped nonce/audit
  tables retain native metadata/quote-strategy authority, not retroactive DBAL identifier restrictions. Changing
  selections performs **no automatic installation, migration, rename, replay-memory transfer or default fallback**.
  See the [complete table inventory](docs/table-names.md).
- Opt-in `ResultSet::strict()` validates page/size/count/total bounds and uses exact integer page arithmetic.
  Legacy construction remains permissive and **not newly deprecated**; both paths retain the mutable list and
  record identities, not a frozen snapshot or permanent validation guarantee. See [repositories](docs/repositories.md#strict-result-construction).
- Optional typed phase activity execution facts: final readonly `ActivityExecution`, `ActivityTarget`,
  `ActivityTiming` and `ActivityResult`, reusing precision-preserving occurrences and fixed elapsed Duration.
  Existing recording/reconstruction, immutable retries, shared activity Query and guarded `execution.activity`
  expose closed target/timing/result data; omitted execution is explicit null. Partial/unknown measurements stay
  honest and observed return/throw/exit/cancellation is never business success. Amend the never-released version-one
  payload directly without historical codecs, new Tools, transaction APIs, automatic capture or consumer adoption.
  Protected-body removal retains safe execution metadata and existing no-resurrection/permission guarantees.
- Reusable `Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork` without ORM dependencies, preserving callback
  results, owned rollback, caller/nested rejection and terminal truthful `DbalTransactionOutcomeUncertain` failures.
  Auto-commit-off connections reject before callback without mode changes or caller-work interference; default
  lazy connections return only after an actual commit.
- Canonical MCP foundation names with identity-preserving released-name compatibility, without protocol,
  authorization, wire/storage or delivery changes. Native saved results from tagged releases remain usable
  when the later optional response budget was absent.
- Canonical MCP HTTP and native framework adapter names grouped under `Adapter\Mcp`, preserving endpoint guards,
  direct JSON, lazy progressive delivery and native response behavior. Internal HTTP helpers move alongside them.
- Opt-in retained-notice removal Commands with independent current admission, finite incarnation-pinned selections,
  atomic relationship/comparison deletion and bounded empty-feed history-loss evidence on SQLite/MySQL/PostgreSQL.
  Trusted coherent storage recovery remains separate from public polling; consumers own retention policy.
  See [safe notices](docs/safe-notices.md#bounded-authorized-removal).
- Five opt-in safe read-only MCP execution inspection Tools plus two separately opted-in protected Detail Tools
  (`execution.messages`, `execution.detail`), all through shared authorized Queries. Tool availability does not
  replace independent current reference/body grants; no removal Tool is exposed. See [MCP inspection](docs/mcp-execution-inspection.md).
- Opt-in `McpToolOutput::boundedStructured()` final encoded JSON-RPC success-response budgets, including text,
  escaping, request identity and central metadata; existing output factories remain unbounded.

### Fixed

- Restore canonical `1.1.0` HMAC `nonces:` named construction alongside later `nonceRepository:` and fourth
  positional injection. Omission/null remains optional; the same object supplied twice consumes once, while
  distinct non-null repositories reject at construction before effects. No storage means no replay protection.
- Restore native `McpToolOutput` objects saved by v1.3.0/v1.4.0 through ordinary Tool invocation: absent later
  response budgets become null/unbounded without losing content, text, Resource links or saved identity. Present
  bounds still survive native round trips and enforce overflow; native unserialization remains trusted-only.
- Contain Tool-list availability callback failures even when they throw a protocol exception: return only generic
  `-32603` / `Internal error.` and make one central diagnostic attempt retaining the original cause. Deliberate
  Common-owned parameter/cursor errors and filter-before-pagination behavior remain unchanged.
- Doctrine nonce consumption and expiry purge honor the configured ORM quote strategy/platform for mapped table
  names; replay/expiry semantics and explicit consumer schema/data responsibilities remain unchanged.
- Reconcile all seven public DBAL table-selection/name declarations and complete operation shapes/counts in the
  compatibility manifest without weakening package-surface checks or changing the authoritative baseline.
- Clarify subscriber inspection's caller-clock semantics and correct its journey evidence: heartbeats later than
  the captured invocation clock remain `stale_uncertain`. This is not a new production liveness fix or resolution
  of the separately documented intermittent Task-live attribution limit.
- Maintainer tooling: disposable database allocation/mount capture now retains uncertainty before fallible inspection. Cleanup
  establishes complete original mount coverage before creator removal; interrupted capture recovers only against
  the original corroborated creator or holds safely, never reporting completion with unknown storage.
- Removed the tracked delivery marker/table/live-check/reconciliation mechanism and recurring intake/post-merge
  bookkeeping. Land owns accepted TASK/current-doc closeout, exact live own-PR validation and eligible local
  resource retirement; historical evidence and human merge remain separate. Planning/routing stays offline.

[Reconciliation scope and deliberate exclusions](docs/maintenance/v1.5.0-reconciliation.md) account for the complete
v1.4.1-to-candidate change set. Earlier v1.4 features and the v1.4.1 Laravel repair are not new v1.5.0 fixes.
These Unreleased notes do not certify, date or approve a release.

## [1.4.1] - 2026-11-04

### Fixed

- Contain terminal JSON-rendering faults in the opt-in Laravel `SafeLaravelExceptionHandler`. If both normal
  presentation and fallback encoding fail, return HTTP 500 with `Content-Type: application/json` and the fixed
  body `{"status":"error","message":"Internal server error."}` without exposing private diagnostics or selected
  failure headers. The original failure receives one diagnostic attempt; renderer faults do not recursively
  enter Laravel reporting. Normal mappings, native response finalization, later requests and HTML delegation
  remain unchanged. Existing opt-in wiring requires no migration; legacy error handlers are unchanged.
- Restore non-root readability of the existing PHP memory/coverage settings and explicitly propagate PHPStan's
  existing 512 MiB limit. Maintenance-native database verification can reuse one retained environment across
  roles and retire it without force only after exact identity and all-consumer checks, including indirect
  network-namespace dependencies. These are maintainer-tooling fixes, not consumer runtime API changes.

## [1.4.0] - 2026-10-12

### Added

- Opt-in strict `Pagination::strict()` construction for checked bounds, ordering and offsets; the legacy
  constructor remains available with its existing permissive behavior.
- Opt-in immutable `JsonObject::fromSnapshot()` and `fromSnapshotString()` for detached JSON-encodable input and
  fresh object-mode output; legacy factories and Doctrine text hydration retain their existing semantics.
- Canonical IPv4/IPv6 values and a lexical E.164 phone value. Existing validation and string-based SMS APIs are
  unchanged; validity does not imply network reachability or permission to contact a number.
- Gregorian Date, local Time, Sunday-zero WeekDay, strict zoned DateTime, fixed microsecond Duration, inclusive
  calendar DateRange and half-open InstantRange. These do not replace native timestamp, audit, scheduler or
  consumer timezone policy contracts.
- Opt-in `Domain\Value\Basic\Decimal` for bounded, string-based exact decimal arithmetic, finite division
  and explicit scale/`RoundingMode` rounding without float or optional arithmetic extensions. Numeric identity is
  canonical; consumers choose financial policy and adoption. See [Decimal values](docs/values.md#decimal).
- A bounded 49-code Currency catalog and exact signed-minor-unit Money with checked arithmetic, explicit rounding
  and deterministic weighted allocation. Consumers own currency eligibility, payment and financial policy; no
  automatic persisted-value migration or exchange-rate handling is introduced.
- Eleven transport-neutral Application failure categories and opt-in safe error presentation for PSR-15,
  Symfony and Laravel. Safe responses use fixed public defaults and consumer-owned projection and redacted
  diagnostics. See [safe HTTP error handling](docs/safe-http-errors.md) for wiring and migration.

### Fixed

- ValidationService and ValidationCoordinator clear pending validators after exceptional exits, retaining the
  original error and allowing subsequent independent validation.
- EmailAddress extracts quoted local parts and domain literals without changing accepted syntax or its stored
  text, case-sensitive identity and separate canonicalization.
- Release certification archives now come from pristine source, excluding build-generated dependencies and
  caches from the local installed-consumer probe. This changes release tooling, not the published runtime API.

### Changed

- **StreamId behavior change (explicit maintainer-approved minor exception):** StreamId now implements Identifier
  with tuple equality, hashing, bytewise ordering and canonical string/JSON reconstruction. Separately constructed
  equal tuples now deduplicate in HashSet, find/remove each other and share a HashTable key (later values replace
  earlier ones). Native JSON changes from `{}` to a string such as `"stream:v1:T3JkZXI=:MTIz"` for `('Order', '123')`.
  This is not backward-compatible for instance-sensitive collections or JSON consumers; the maintainer approved
  minor delivery after reporting no external StreamId adopters. Constructor/getters, nonempty byte-string inputs
  and persisted aggregate-name/identifier fields remain unchanged; no schema migration is required. Rebuild old
  serialized hash collections containing StreamIds; choose a list or consumer-owned instance identity policy if
  duplicate instances must remain distinct. See [migration guidance](docs/event-sourcing.md#streamid-minor-upgrade-notice).
- Hosted CI is advisory by default for Fight Common PR delivery; the full local product gate and independent
  technical review remain required. A specifically accepted or host-enforced check remains binding.

### Deprecated

- The public `Pagination::__construct()` is deprecated in PHPDoc/documentation in favor of
  `Pagination::strict()`; it remains functional with its permissive behavior in 1.x and emits no runtime
  deprecation warning. See [legacy pagination and migration](docs/repositories.md#legacy-pagination-and-migration).
- Legacy HTTP error paths that expose arbitrary exception messages remain operational in 1.x but are unsafe for
  untrusted clients; opt into the safe PSR-15/Symfony/Laravel handlers. Replacing legacy defaults waits for a
  separately approved major release.

## [1.3.0] - 2026-10-02

### Added

- A bounded stateless MCP `2026-07-28` server surface: semantic JSON-RPC dispatch, capability discovery,
  explicit Tool registration, available-only listing and validated calls, and a guarded PSR-15 POST endpoint
  checking Origin, negotiation, transport mirrors and a consumer invocation guard. Common provides neither
  routes nor application authentication or authorization.
- Opt-in MCP progress and cooperative cancellation with progressive SSE delivery through a PHP-SAPI emitter;
  documented Symfony, Laravel, Yii, Slim and CodeIgniter composition paths. Direct JSON remains the default.
  Package loopback evidence does not qualify other runtimes, buffering layers or proxies.
- Protected ordinary `input_required` Tool interactions with stateless form retries and separate atomic
  single-use destructive confirmation backed by a consumer store. Consumers own caller binding, permission
  policy, keys, destructive classification and store persistence.
- Explicit Resource discovery and exact, availability-filtered text/binary reads; immutable Skill revision files
  served as Resources; complete `skills/list` and `skills/get` over shared Resource providers. The combined
  Tool/Resource/Skill endpoint rechecks availability and bounds catalog, read and result work. Consumers own
  Skill and Resource acquisition, storage and authorization policy.
- A policy-free OAuth resource-server boundary usable with MCP, with consumer-supplied token validation,
  claim/scope checks and authentication handoff; Common neither issues tokens nor decides business permissions.
- `Domain\Value\Basic\StrictJson` for immutable, type-preserving bounded JSON values used by MCP contracts.

### Changed

- **Pre-release MCP API change:** `McpTool::handle()` now permits `McpToolOutput|McpInputRequired`; non-interactive
  Tools may retain the narrower `McpToolOutput` return. Earlier unreleased MCP raw-object/`JsonObject` accessors
  use `StrictJson`; callers of the base interface must handle interactive results. Tool/Resource/Skill discovery
  and read APIs use finite budgets and request-scoped availability; dynamic shared Resource catalogs are checked
  on admitted Skill dispatch rather than during Skill service construction.
- Fight Common's own PHPCS gate enforces its documented strict multiline-docblock and declaration layout rules
  instead of allowing suppression comments. Existing Doctrine DBAL schema builders retain compatibility with
  the `^4.4` dependency range.

MCP support is deliberately scoped: no composed Prompts, resource subscriptions, Skill directory RPC, URL-mode
elicitation, client-to-server notifications or framework-starter/installed-consumer qualification is implied.
Pinned composed-endpoint conformance results include scenario-specific SHOULD warnings, skips and an uncomposed
Prompts failure; they are not a blanket MCP conformance pass. See the [MCP guide](docs/components/mcp/index.md)
for exact limits and composition requirements.

## [1.2.0] - 2026-09-12

### Added

- Framework-neutral Event Sourcing: aggregate recording, replay, and reconstitution; stable stream and stored-event contracts; mapping, schema upcasting, and aggregate repositories; in-memory and Doctrine DBAL event stores for SQLite, MySQL-compatible, and PostgreSQL databases; checkpointed projections; and durable, diagnosable event publication. Symfony applications can autoconfigure `EventMappingProvider` services, and `docs/event-sourcing.md` provides an executable integration and operations guide.
- Canonical Symfony adapter paths for HTTP exception and validation subscribers, JSON request middleware, error controllers, local filesystems, and URL generation. The new paths are covered by Symfony registration and old/new interoperability probes.
- `Adapter\Filesystem\Laravel\LaravelFilesystem`, a complete local-filesystem adapter that composes Laravel's native filesystem, fills its missing metadata and ownership operations with PHP primitives, and is selected by Laravel's capability-scoped filesystem provider.
- Laravel asynchronous command/event adapters and private broadcast publication, selected by the bounded messaging
  and broadcasting providers so consuming applications own only handlers, queue operations, channels, and authorization.
- `Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork`, the canonical Doctrine adapter for the narrow `Application\Repository\TransactionalUnitOfWork` boundary.

### Fixed

- `HmacRequestService` now emits `X-Timestamp` as a PSR-7-compatible string while retaining the same integer
  timestamp for canonical request and signature calculation.

### Deprecated

- `Adapter\Cache\PsrCache` — replaced by `Adapter\Cache\Psr6\Psr6Cache`. The legacy class remains functional without runtime notices throughout 1.x and will be removed in 2.0.
- `Adapter\HttpFoundation\JSendResponse` — replaced by the typed `Adapter\Http\Symfony\JSendResponse`. The legacy raw-array response remains functional throughout 1.x and will be removed in 2.0.
- `Adapter\Repository\DoctrineUnitOfWork` and `Application\Repository\UnitOfWork::commit()` — replaced for new consumers by `Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork` and `Application\Repository\TransactionalUnitOfWork::commitTransactional()`. The legacy Repository-path adapter and standalone commit journey remain functional without runtime notices throughout 1.x.
- `Adapter\Messaging\Command\Async\MessengerCommandBus`, `Adapter\Messaging\Event\Async\MessengerEventDispatcher`, `Adapter\Messaging\Serializer\SymfonyMessageSerializer`, `Adapter\Messaging\Handler\SymfonyCommandMessageHandler`, and `Adapter\Messaging\Handler\SymfonyEventMessageHandler` — replaced by the canonical `Adapter\Messaging\Symfony\...` and transport-neutral `Adapter\Messaging\Handler\...` paths. Legacy names remain functional throughout 1.x and will be removed in 2.0.
- `Adapter\EventSubscriber\SymfonyExceptionSubscriber`, `Adapter\EventSubscriber\SymfonyValidationSubscriber`, `Adapter\HttpKernel\JsonRequestMiddleware`, `Adapter\HttpKernel\ErrorController`, `Adapter\Filesystem\SymfonyFilesystem`, and `Adapter\Routing\SymfonyUrlGenerator` — replaced by their capability-first Symfony paths. Legacy names remain functional without runtime notices throughout 1.x and will be removed in 2.0.
- `Domain\Serialization\JsonSerializer` — replaced by `Application\Serialization\JsonSerializer`. The Domain class remains a standalone deprecated compatibility implementation throughout 1.x and will be removed in 2.0.
- `Domain\Serialization\PhpSerializer` — replaced by `Application\Serialization\PhpSerializer`. The Domain class remains a standalone deprecated compatibility implementation throughout 1.x and will be removed in 2.0.
- `Domain\Auth\AiOperation` — will be removed in 2.0. MCP/AI operation tooling will be redesigned as a future feature.
- `Application\Auth\WebhookDispatcher` — will be removed in 2.0 alongside the deprecated outbound webhook operation path.
- `Adapter\Auth\Hmac\HmacWebhookDispatcher` — will be removed in 2.0. The HMAC authentication layer (`HmacAuthenticator`, `HmacRequestService`) remains unaffected.

### Changed

- Typed JSend now has `Application\Http\JSend\JSendEnvelope` and `JSendStatus` semantics, while `Adapter\Http\Symfony\JSendResponse` preserves controller-selected HTTP metadata and renders `Arrayable` and paginated `ResultSet` presentation data.
- Symfony Messenger now uses canonical command and event bus and serializer paths, with transport-neutral `CommandMessageHandler` and `EventMessageHandler` for handing messages from a worker to synchronous application buses.
- Message envelopes now isolate their mutable `Meta` snapshots by copying metadata on construction and access. Code that previously mutated metadata through `meta()` must derive a same-ID envelope with `withMeta()` or `mergeMeta()` instead.
- **Corrected historical description:** portable Scheduler composition uses `Scheduler::withProcessRunner()`
  and builds command jobs through `ProcessBuilder`. The exact legacy constructor order (`timezone`, `tempDirectory`,
  `logger`, `mailService`, `fromEmail`, `processFactory`) remains supported, deprecated since v1.2.0, with the
  conditional Symfony Process bridge. No third-position ProcessRunner requirement or 1.x seam removal occurred;
  see [ADR 0017](planning/adr/0017-scheduler-1x-construction-compatibility.md).
- Pinned all `symfony/*` `require-dev` components to the `^8.1` floor per ADR 0020's current-only support window. `symfony/process` moves from `^7.0` to `^8.1`. The widened `^8.2 || ^8.1` form will be adopted only when Symfony 8.2 ships (~Nov 2026).

## [1.1.0] - 2026-06-03

### Added

**PhpEngine Hardening**
- **Output buffer leak on exception** — `evaluate()` now wraps `require` in try/finally so `ob_get_clean()` is always called even when the template throws
- **`ob_get_clean()` null-check** — `endBlock()` and `evaluate()` both guard against `ob_get_clean()` returning `false` (throws `TemplatingException`)
- **Path traversal protection** — `exists()` and `getTemplatePath()` validate resolved paths against allowed base directories via `realpath()`; symlink escapes raise `TemplateNotFoundException`
- **Hash cache key** — replaced `hash('sha256', $file)` with the direct file path as the cache key, avoiding unnecessary hashing
- **Parent-template inheritance** — the dead `@codeCoverageIgnore`d code in `render()` was properly implemented: `extends()` called during template evaluation now triggers parent rendering, enabling layout inheritance via child blocks
- **`$template` mutation bug** — `getTemplatePath()` no longer mutates the `$template` parameter inside the `foreach` loop (uses a local `$resolved` variable instead)
- **`TemplatingException` coverage** — added dedicated test with `#[CoversClass]` attribution

**Observability Layer — Domain**
- `Domain\Observability\HealthStatus` — value object with severity-ordered statuses (`healthy`, `degraded`, `unhealthy`) and `worst()` aggregation
- `Domain\Observability\HealthResult` — per-check record (name, status, optional message and context)
- `Domain\Observability\HealthReport` — aggregated report with `fromResults()` factory (computes overall as worst-of); `isHealthy()` gate; JSON-serializable
- `Domain\Observability\AuditEntryId` — typed unique identifier for audit entries
- `Domain\Observability\AuditEntry` — structured business-fact record (actor, action, timestamp, context); `record()` factory; JSON-serializable; `readonly` (no longer `final` for Doctrine ORM compatibility)
- `Domain\Observability\AuditRepository` — queryable audit storage port (moved from Application layer): `getByActor()`, `getByAction()`, `getBetween()`, `add()`

**Observability Layer — Application Ports**
- `Application\Observability\HealthCheck` — interface: `check(): HealthResult`, `name(): string`
- `Application\Observability\HealthAggregator` — interface: `addCheck()`, `report(): HealthReport`
- `Application\Observability\MetricsCollector` — interface: `increment()`, `gauge()`, `histogram()` with tag support
- `Application\Observability\AuditLog` — interface: `record(AuditEntry): void`

**Observability Layer — Adapters**
- `Adapter\Observability\Health\HealthReporter` — aggregates N `HealthCheck` implementations into a `HealthReport`
- `Adapter\Observability\Health\DatabaseHealthCheck` — Doctrine DBAL ping with latency measurement
- `Adapter\Observability\Health\HttpEndpointHealthCheck` — HTTP reachability check (2xx = healthy)
- `Adapter\Observability\Metrics\NullMetricsCollector` — no-op default; zero overhead
- `Adapter\Observability\Metrics\StatsDMetricsCollector` — DogStatsD UDP adapter with tag support; injectable sender closure for testability
- `Adapter\Observability\Audit\NullAuditLog` — no-op default
- `Adapter\Observability\Audit\LoggingAuditLog` — writes structured audit JSON to any PSR-3 logger
- `Adapter\Messaging\Command\MetricsCommandFilter` — command bus middleware; auto-emits `command.executed`, `command.failed`, `command.latency_ms`
- `Adapter\Messaging\Query\MetricsQueryFilter` — query bus middleware; auto-emits `query.executed`, `query.failed`, `query.latency_ms`

**HMAC-Secured AI Operations API**
- `Domain\Auth\Nonce` — value object with hex value and expiry; `generate()` factory for unique nonces
- `Domain\Auth\AiOperation` — signed AI operation record; validates against known actions (`health_check`, `clear_cache`, `run_migration`, `deploy`); `fromJson()` / `fromArray()` factories
- `Application\Auth\NonceRepository` — port: `consume(Nonce)` (throws `AuthException` on replay) and `purgeExpired()`
- `Application\Auth\WebhookDispatcher` — port: `dispatch(string $url, string $action, array $payload): void`
- `Adapter\Auth\Nonce\InMemoryNonceRepository` — single-process nonce store; good for testing and short-lived workers
- `Adapter\Auth\Nonce\DoctrineNonceRepository` — persists to `hmac_nonces` table; atomic replay prevention via unique constraint
- `Adapter\Auth\Hmac\HmacWebhookDispatcher` — builds, signs, and sends AI operation requests; composes `HttpClient` + `HmacRequestService`

**Doctrine Audit Repository**
- `Adapter\Repository\DoctrineRepository` — abstract base class providing `createQueryBuilder()` and `createPaginator()` helpers for Doctrine ORM repositories
- `Adapter\Repository\DoctrineAuditRepository` — implements `AuditRepository`; persists `AuditEntry` via Doctrine ORM; supports pagination with ordering and all query methods
- `Adapter\Doctrine\AuditEntryIdDataType` — custom DBAL type for `AuditEntryId` (UUID-backed)
- `Adapter\Doctrine\MetaDataType` — custom DBAL type for `Meta` (JSON-backed)
- `database/schema/Observability.AuditEntry.orm.xml` — Doctrine ORM XML mapping for the `audit_entries` table

**Scheduler Module**
- `Application\Scheduler\Scheduler` — cron-driven job runner; supports cron expressions, datetime strings, and callable schedules; file-based exclusive locking; per-job output capture (stdout, file path, or suppressed); configurable max-runtime guard; error logging via `LoggerInterface` and failure notification via `MailService`; injectable `processFactory` for testable shell command dispatch
- `Application\Scheduler\Exception\SchedulerException` — extends `SystemException`; raised on job failure, bad return value, or non-zero exit code
- `Application\Scheduler\Exception\LockException` — extends `SchedulerException`; raised when a job's lock file is held by another process
- `Domain\Value\DateTime\Timezone` — immutable value object wrapping `DateTimeZone` with construction-time validation

**Process Module**
- `Application\Process\Process` — immutable value object representing a completed process result (command, exit code, stdout, stderr)
- `Application\Process\ProcessRunner` — port: `run(string $command, ProcessErrorBehavior $onError): Process`
- `Application\Process\ProcessErrorBehavior` — enum controlling whether a non-zero exit code throws or returns
- `Application\Process\Exception\ProcessException` — extends `SystemException`
- `Application\Process\Exception\ProcessFailedException` — extends `ProcessException`; raised on non-zero exit when `ProcessErrorBehavior::THROW` is set
- `Adapter\Process\Symfony\SymfonyProcessRunner` — implements `ProcessRunner` via `symfony/process`

**FileTransfer Module**
- `Application\FileTransfer\Resource\Resource` — immutable value object representing a file resource (path, name, type)
- `Application\FileTransfer\Resource\ResourceType` — enum of supported resource types
- `Application\FileTransfer\Transport\FileTransport` — port: `upload(Resource): void`, `download(string $remote): Resource`, `delete(string $remote): void`, `list(string $directory): array`
- `Application\FileTransfer\FileTransferService` — composes a `FileTransport` with higher-level transfer helpers
- `Application\FileTransfer\Exception\FileTransferException` — extends `SystemException`
- `Adapter\FileTransfer\Sftp\SftpFileTransport` — implements `FileTransport` via `phpseclib/phpseclib` SFTP
- `Adapter\FileTransfer\Ftp\FtpFileTransport` — implements `FileTransport` via PHP's native FTP extension
- `Adapter\FileTransfer\Logging\LoggingFileTransport` — PSR-3 logging decorator for any `FileTransport`
- `Adapter\FileTransfer\Null\NullFileTransport` — no-op adapter for testing

**SMS Module**
- `Application\Sms\Message\SmsMessage` — immutable message value object (to, from, body, media URLs); fluent `setBody()`, `addMedia()`
- `Application\Sms\Message\SmsFactory` — port: `createMessage(to, from, body?, mediaUrls[])` with auto-parser for URL strings and `Url` objects
- `Application\Sms\Transport\SmsTransport` — port: `send(SmsMessage): void` (throws `SmsException`)
- `Application\Sms\SmsService` — implements both `SmsTransport` and `SmsFactory`; decorates a transport with factory capabilities
- `Application\Sms\Exception\SmsException` — extends `SystemException`

**SMS Adapters**
- `Adapter\Sms\Null\NullSmsTransport` — no-op adapter (mirrors `NullMailTransport`)
- `Adapter\Sms\Logging\LoggingSmsTransport` — PSR-3 logging decorator for any `SmsTransport` (mirrors `LoggingMailTransport`)
- `Adapter\Sms\Twilio\TwilioSmsTransport` — Twilio Programmable SMS adapter; maps `SmsMessage` to `$client->messages->create()`; wraps `TwilioException` in `SmsException`

**Documentation**
- `docs/observability.md` — full wiring guide for health checks, metrics, audit log, and HMAC AI operations

### Changed

- `HmacAuthenticator::validate()` now throws `AuthException` on signature mismatch instead of returning `false`. The return type is narrowed from `bool` to `true`. Callers using `if (!$authenticator->validate($request))` should be updated to call `$authenticator->validate($request)` directly (the exception replaces the falsy check path). The method also accepts an optional `NonceRepository` to prevent replay attacks within the timestamp tolerance window.

---

## [1.0.0] - 2026-05-22

### Added

**Domain layer**
- Value objects: `StringObject`, `MbStringObject`, `JsonObject`, `EmailAddress`, `Uri`, `Url`, `Uuid`, `UniqueId`, `MessageId`
- Typed collection hierarchy: `ArrayList`, `HashSet`, `HashTable`, `SortedSet`, `SortedTable`, `ArrayStack`, `LinkedStack`, `ArrayQueue`, `LinkedQueue`, `LinkedDeque`
- Ordered structures backed by `RedBlackSearchTree` with floor, ceiling, rank, and range operations
- Specification pattern via `CompositeSpecification` with `and()`, `or()`, `not()` combinators
- CQRS domain message primitives: `CommandMessage`, `QueryMessage`, `EventMessage`, `Meta`
- Domain serialization: `JsonSerializer`, `PhpSerializer`, and `Serializable` interface
- Domain exception hierarchy: `DomainException`, `TypeException`, `ValueException`, `AssertionException`, and more
- Type system: `Comparable`, `Comparator`, `Equatable`, `Arrayable`, `Type`

**Application layer**
- `CommandBus` and `QueryBus` with pipeline middleware support (`CommandFilter`, `QueryFilter`)
- `EventDispatcher` with synchronous and asynchronous variants
- Declarative validation via `#[Validation]` attribute with 60+ built-in rules
- `ValidationService`, `ValidationCoordinator`, and `ValidationResult`
- PSR-11 compatible `Container` with singleton and factory registration
- Ports (interfaces) for: `Auth`, `Cache`, `FileStorage`, `Filesystem`, `HttpClient`, `Mail`, `Routing`, `Socket`, `Templating`, `Repository`

**Adapter layer**
- 11 custom Doctrine DBAL types: `UuidDataType`, `UriDataType`, `UrlDataType`, `EmailAddressDataType`, `StringObjectDataType`, `StringTextDataType`, `MbStringObjectDataType`, `MbStringTextDataType`, `JsonObjectDataType`, `TypeDataType`, `MessageDataType`
- `DoctrineUnitOfWork` adapter
- 6 Symfony DI compiler passes: `CommandHandlerCompilerPass`, `CommandFilterCompilerPass`, `QueryHandlerCompilerPass`, `QueryFilterCompilerPass`, `EventSubscriberCompilerPass`, `TemplateHelperCompilerPass`
- `SymfonyValidationSubscriber` — auto-wires `#[Validation]` for Symfony controller actions
- Guzzle HTTP adapter: `GuzzleHttpClient`, `GuzzleMessageFactory`, `GuzzleStreamFactory`, `GuzzleUriFactory`
- `LoggingHttpClient` — PSR-3 logging decorator for any `HttpClient`
- JWT adapter via `lcobucci/jwt`: `JwtEncoder`, `JwtDecoder`
- Password adapter: `PhpPasswordHasher`, `PhpPasswordValidator`
- HMAC request signing: `HmacAuthenticator`, `HmacMethods`, `HmacKeyGenerator`
- `FlysystemStorage` — file storage adapter via `league/flysystem`
- `SymfonyFilesystem` — local filesystem adapter via `symfony/filesystem`
- `SymfonyMailTransport` and `SymfonyAttachment` via `symfony/mailer`
- `LoggingMailTransport` and `NullMailTransport`
- `PhpEngine`, `TwigEngine`, `DelegatingEngine` — template engine adapters
- `SymfonyUrlGenerator` — URL generation via `symfony/routing`
- `MercureHubPublisher` — real-time push via `symfony/mercure`
- `JSendResponse` and `JsonRequestMiddleware` via `symfony/http-foundation` / `symfony/http-kernel`
- `PsrCache` — cache-through adapter for any PSR-6 pool
- `SimpleEventDispatcher` and `ServiceAwareEventDispatcher`

**Quality**
- 100% statement coverage enforced via PHPUnit `requireCoverageMetadata`
- PHP 8.5+ required
- PSR-3, PSR-6, PSR-7, PSR-11, PSR-17, PSR-18 interface compliance

[Unreleased]: https://github.com/johnnickell/fight-common/compare/v1.4.1...HEAD
[1.4.1]: https://github.com/johnnickell/fight-common/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/johnnickell/fight-common/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/johnnickell/fight-common/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/johnnickell/fight-common/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/johnnickell/fight-common/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/johnnickell/fight-common/releases/tag/v1.0.0
