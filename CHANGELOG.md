# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

**SMS Module**
- `Application\Sms\Message\SmsMessage` — immutable message value object (to, from, body, media URLs); fluent `setBody()`, `addMedia()`
- `Application\Sms\Message\SmsFactory` — port: `createMessage(to, from, body?, mediaUrls[])` with auto-parser for URL strings and `Url` objects
- `Application\Sms\Transport\SmsTransport` — port: `send(SmsMessage): void` (throws `SmsException`)
- `Application\Sms\SmsService` — implements both `SmsTransport` and `SmsFactory`; decorates a transport with factory capabilities
- `Application\Sms\Exception\SmsException` — extends `SystemException`

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

[Unreleased]: https://github.com/johnnickell/fight-common/compare/1.0.0...HEAD
[1.0.0]: https://github.com/johnnickell/fight-common/releases/tag/1.0.0
