# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

—

### Deprecated

- `Domain\Serialization\JsonSerializer` — replaced by `Application\Serialization\JsonSerializer`. The Domain class remains a standalone deprecated compatibility implementation throughout 1.x and will be removed in 2.0.
- `Domain\Serialization\PhpSerializer` — replaced by `Application\Serialization\PhpSerializer`. The Domain class remains a standalone deprecated compatibility implementation throughout 1.x and will be removed in 2.0.
- `Domain\Auth\AiOperation` — will be removed in 2.0. MCP/AI operation tooling will be redesigned as a future feature.
- `Application\Auth\WebhookDispatcher` — will be removed in 2.0 alongside the deprecated outbound webhook operation path.
- `Adapter\Auth\Hmac\HmacWebhookDispatcher` — will be removed in 2.0. The HMAC authentication layer (`HmacAuthenticator`, `HmacRequestService`) remains unaffected.

### Changed

- Message envelopes now isolate their mutable `Meta` snapshots by copying metadata on construction and access. Code that previously mutated metadata through `meta()` must derive a same-ID envelope with `withMeta()` or `mergeMeta()` instead.
- `Application\Scheduler\Scheduler` now requires an Application `ProcessRunner` as its third constructor argument and builds command jobs through `ProcessBuilder`; the former Scheduler `processFactory` constructor seam has been removed.
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

[Unreleased]: https://github.com/johnnickell/fight-common/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/johnnickell/fight-common/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/johnnickell/fight-common/releases/tag/v1.0.0
