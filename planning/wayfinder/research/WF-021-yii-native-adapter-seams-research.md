# Yii-native adapter seams

**Research ticket:** [Research Yii-native adapter seams](../tickets/WF-021-yii-native-adapter-seams.md)
**Researched:** 2026-08-22
**Scope:** current Yii 3 packages and the live Fight Common Domain, Application, and Adapter contracts

## Finding

Yii 3 can support the full Fight Common capability spectrum, but it should not be treated as one
framework distribution. It is a set of independently versioned packages assembled through
`yiisoft/config` and `yiisoft/di`. The reusable Yii integration should therefore be a collection of
**capability-scoped configuration and service providers**, not one provider that installs every
optional package. Yii's package guide explicitly supports `params`, `di`, `di-providers`, `routes`,
`events`, and their web/console variants through Composer `extra.config-plugin`; DI providers implement
`Yiisoft\Di\ServiceProviderInterface` and return definitions or extensions
([package design](https://yiisoft.github.io/docs/guide/structure/designing-packages.html),
[configuration](https://yiisoft.github.io/docs/guide/concept/configuration.html),
[Yii DI](https://github.com/yiisoft/di)).

The stable, worthwhile native adapters are small:

1. `Adapter\Persistence\Yii\YiiDbTransactionalUnitOfWork` for the additive narrow
   `TransactionalUnitOfWork`, not the legacy Doctrine-shaped `UnitOfWork`;
2. `Adapter\Routing\Yii\YiiUrlGenerator`;
3. a Yii Mailer transport bridge, subject to a complete Fight `MailMessage` conformance test;
4. a Yii View engine bridge for projects choosing Yii view conventions;
5. capability-scoped `Adapter\ServiceContainer\Yii\...ServiceProvider` classes and Yii config-plugin
   entry points.

Most other facilities should reuse Fight Common's PSR/standalone adapters. In particular, Yii is
already PSR-7/15/17/18-oriented, its cache and logger packages expose standard interfaces, its retired
filesystem and HTTP-client packages point consumers at standalone standards, and its container is
PSR-11-compatible. A PSR-7 JSend implementation is useful, but belongs in a neutral
`Adapter\Http\Psr7` namespace shared by Yii and Slim rather than in `Adapter\Http\Yii`.

Async messaging is the exception. The current `yiisoft/queue` API is a technically good seam for Fight
commands and events, including retry middleware and worker commands, but it remains an unreleased
development dependency. Its manifest declares `minimum-stability: dev` and aliases `dev-master` to
`3.0.x-dev`; GitHub has no releases. The official AMQP and Redis adapters likewise require the queue's
development branch. It must not be a stable Fight Common dependency or a promised 1.2 adapter until the
core and selected broker adapter have stable tags
([queue manifest](https://github.com/yiisoft/queue/blob/master/composer.json),
[queue releases](https://github.com/yiisoft/queue/releases),
[official adapter list](https://github.com/yiisoft/queue/blob/master/docs/guide/en/adapter-list.md),
[AMQP manifest](https://github.com/yiisoft/queue-amqp/blob/master/composer.json),
[Redis manifest](https://github.com/yiisoft/queue-redis/blob/master/composer.json)).

## Classification

- **Reusable adapter** — framework/package types implement a Fight Application contract and belong in
  Fight Common behind an optional Composer dependency.
- **ServiceContainer/config provider** — reusable registration only; the provider is capability-scoped
  and must not require unrelated adapters.
- **Neutral adapter reuse** — a live Fight adapter already composes with Yii through PSR or standalone
  contracts.
- **Standalone provider** — a non-Yii library is the supported implementation and Yii only wires it.
- **Starter-only configuration** — application identity, credentials, routes, broker topology, worker
  supervision, or other project policy that Fight Common cannot safely choose.

## Capability sweep

| Capability | Current Yii seam and stability | Decision for Fight Common | Starter responsibility |
| --- | --- | --- | --- |
| Service container | `yiisoft/di` is PSR-11; Yii packages publish definitions and service providers through `yiisoft/config`. The current official app constrains `yiisoft/di ^1.4.1` and `yiisoft/config ^1.6.2` ([app manifest](https://github.com/yiisoft/app/blob/master/composer.json)). | **ServiceContainer/config provider.** Add separate providers such as `CommandBusServiceProvider`, `PersistenceServiceProvider`, `MailServiceProvider`, and `ViewServiceProvider`, each with only its own optional dependency. Publish appropriate `di`/`di-providers` config-plugin entries. | Select providers, aliases, handler maps, environment parameters, and web versus console groups. |
| Authentication | `yiisoft/auth` authenticators consume a PSR-7 request and return an `IdentityInterface` or `null`; challenge-capable authenticators have a distinct contract, and middleware places identity in request processing ([auth package](https://github.com/yiisoft/auth), [authenticator interface](https://github.com/yiisoft/auth/blob/master/src/AuthenticatorInterface.php)). Fight `Authenticator::validate()` returns only `bool`. | **Starter composition first; optional reusable adapter only with an explicit lossy contract.** A wrapper can map non-null identity to `true`, but it discards identity and challenge semantics. Do not make it the default principal bridge. Fight's neutral HMAC/JWT authenticators and PSR-15 middleware remain reusable. | Implement the project identity repository and principal mapping; register auth middleware and challenges; read the authenticated identity request attribute in project/application code. |
| Cache | `yiisoft/cache` wraps any PSR-16 handler and supplies official APCu, DB, file, Memcached, Redis, and WinCache handlers ([cache package](https://github.com/yiisoft/cache)). | **Neutral adapter reuse plus provider.** Bind a PSR cache implementation to Fight's existing cache adapter; do not create `YiiCache`. If the live Fight adapter remains PSR-6-only, use a tested PSR-6 provider/bridge rather than pretending PSR-6 and PSR-16 are identical. | Choose backend, namespace, TTL defaults, connection, and invalidation policy. |
| Persistence and transactions | `yiisoft/db` is a framework-agnostic DBAL. `ConnectionInterface::transaction(Closure)` returns the callback result and rolls back on thrown failure; explicit transactions support commit, rollback, activity, nesting, and savepoints ([DB package](https://github.com/yiisoft/db), [connection contract](https://github.com/yiisoft/db/blob/master/src/Connection/ConnectionInterface.php), [transaction contract](https://github.com/yiisoft/db/blob/master/src/Transaction/TransactionInterface.php)). | **Reusable adapter:** `YiiDbTransactionalUnitOfWork` implements only the new narrow `TransactionalUnitOfWork`. It preserves the callback result, rejects unsupported nesting according to the Fight contract, translates terminal state consistently, and never invents Doctrine-style `flush()`/`commit()`. | Configure driver/DSN/isolation and compose project repositories inside the transaction. |
| Repositories / Active Record | Yii DB and `yiisoft/active-record` provide record/query persistence, not a universal mapping for Fight aggregates ([Active Record](https://github.com/yiisoft/active-record)). | **Starter-owned/project adapter.** Move generic Fight repository infrastructure to `Adapter\Persistence`, but do not add a generic Yii repository base that leaks records into Domain. | Hydrate aggregates, preserve nullable lookup/pagination/relationship-replacement behavior, and own migrations. |
| File storage | The former `yiisoft/yii-filesystem` package is archived and tells consumers to use Flysystem directly ([archived package](https://github.com/yiisoft/yii-filesystem)). | **Neutral adapter reuse.** Use Fight's Flysystem storage adapter. No Yii storage wrapper. | Select local/S3/etc adapter, roots, visibility, URLs, and credentials. |
| Filesystem | `yiisoft/files` offers standalone local file/directory helpers, but it does not supply the broad stateful Fight `Filesystem` contract ([Yii Files](https://github.com/yiisoft/files)). | **Standalone provider.** Continue to wire the existing Symfony Filesystem-based adapter, or later add a native-PHP neutral adapter. A Yii dependency adds no useful boundary. | Select allowed roots and permissions. |
| Outbound HTTP client | The old Yii HTTP client is archived and explicitly recommends a PSR-18 client instead ([archived HTTP client](https://github.com/yiisoft/yii-http-client)). | **Neutral/standalone reuse.** Keep Fight's Guzzle/PSR client and message factories; add no Yii HTTP client adapter. | Configure base URIs, authentication, timeouts, retries, and telemetry. |
| Request, response, and JSend | Current Yii web applications use PSR-7/17 request/response types and the Yii HTTP runner; the official app uses `httpsoft/http-message ^1.1.6` and `yiisoft/yii-http ^1.1.1` ([app manifest](https://github.com/yiisoft/app/blob/master/composer.json), [Yii HTTP](https://github.com/yiisoft/yii-http)). `yiisoft/data-response` can format data as PSR-7 responses ([data response](https://github.com/yiisoft/data-response)). | **Neutral reusable adapter:** create `Adapter\Http\Psr7\JSendResponseFactory` against PSR-17 response and stream factories. Keep Fight's HTTP method/status values in `Application\Http`; do not replace the public contract with `yiisoft/http` constants. | Bind the selected PSR-17 factories and translate controller/application results at the edge. |
| Middleware and error handling | `yiisoft/middleware-dispatcher` dispatches PSR-15 middleware using PSR-11 and PSR-14 and is constrained at `^5.4` by the official app ([dispatcher](https://github.com/yiisoft/middleware-dispatcher), [app manifest](https://github.com/yiisoft/app/blob/master/composer.json)). | **Neutral PSR-15 reuse plus provider.** Middleware without framework imports belongs under `Adapter\Middleware\Psr15`; Yii-specific error-controller glue, if actually needed, may live under `Adapter\Http\Yii\Controller`. | Order the pipeline, configure route middleware/error presentation, and avoid exposing exceptions in production. |
| Logging | `yiisoft/log` is PSR-3-compatible, supports multiple targets and flushing, and the official app requires `^2.2.0` ([Yii Log](https://github.com/yiisoft/log), [app manifest](https://github.com/yiisoft/app/blob/master/composer.json)). | **Neutral adapter reuse plus provider.** Bind Yii's PSR-3 logger to Fight logging audit/mail/SMS/socket decorators. No `YiiLogger` adapter. Monolog remains an equally valid standalone provider. | Choose targets, redaction, levels, correlation context, buffering, and flush lifecycle. |
| Health, audit, and metrics | `yiisoft/yii-debug` is development diagnostics, not a production implementation of Fight `HealthCheck`, `AuditLog`, or `MetricsCollector` ([Yii Debug](https://github.com/yiisoft/yii-debug)). | **Neutral/standalone providers.** Reuse Fight health aggregation and logging audit; wire OpenTelemetry/StatsD/Prometheus adapters independently if adopted. | Expose protected health endpoints, define readiness, metrics export, audit retention, and dev-only debug policy. |
| Process management | Yii Console uses Symfony Console for commands; Yii does not provide a general subprocess execution component. Queue documentation recommends real process supervisors for long-running consumers ([Yii Console](https://github.com/yiisoft/yii-console), [queue process managers](https://github.com/yiisoft/queue/blob/master/docs/guide/en/process-managers.md)). | **Standalone provider.** Reuse Fight's Symfony Process runner; do not create a Yii process adapter. | Define commands, timeouts, environment allowlists, systemd/Supervisor units, deployment restarts, and signals. |
| URL generation / routing | `yiisoft/router` provides `generate()` and `generateAbsolute()` with named route arguments and query parameters; the official app requires `^4.0.2` ([URL generator](https://github.com/yiisoft/router/blob/master/src/UrlGeneratorInterface.php), [app manifest](https://github.com/yiisoft/app/blob/master/composer.json)). | **Reusable adapter:** `YiiUrlGenerator` maps Fight's name/parameters/query/absolute flag directly and translates route failures to `UrlGenerationException`. | Configure routes, base URI/host/scheme, and deployment prefix. |
| Mail | `yiisoft/mailer` defines transport-independent message and mailer contracts; official Yii implementations include Symfony Mailer, file, stub, and null transports ([Mailer](https://github.com/yiisoft/mailer), [mail guide](https://yiisoft.github.io/docs/guide/tutorial/mailing.html), [Symfony Mailer bridge](https://github.com/yiisoft/mailer-symfony)). | **Reusable adapter, after conformance proof:** map Fight `MailMessage` addresses, multipart bodies, attachments, sender/return path, charset, priority, and failures to Yii Mailer. Keep the existing Fight Symfony Mailer adapter as a valid standalone alternative. | Select transport, DSN, defaults, queues, credentials, and dev capture. |
| SMS | No current first-party Yii package provides a Fight `SmsTransport`. | **Standalone provider.** Reuse Twilio, logging, or null transports; provider wiring only. | Choose vendor, sender, credentials, rate limits, and delivery callbacks. |
| Socket / push | No current first-party Yii package supplies Fight's publish contract. | **Standalone provider.** Reuse Mercure (or add an independently tested Pusher provider); no Yii socket adapter. | Configure hub/vendor credentials, topic authorization, reverse proxy/CORS, subscriber token minting, and topology. |
| Templating | `yiisoft/view` renders PHP views and supports state clearing; `yiisoft/yii-view-renderer` turns views into web responses. The official app requires `yiisoft/view ^12.2.4` and `yiisoft/yii-view-renderer ^7.4.1` ([Yii View](https://github.com/yiisoft/view), [web renderer](https://github.com/yiisoft/yii-view-renderer), [app manifest](https://github.com/yiisoft/app/blob/master/composer.json)). Twig is also available independently ([Yii Twig view](https://github.com/yiisoft/view-twig)). | **Reusable adapter:** a `YiiViewEngine` is justified for Yii layouts, themes, and PHP-view conventions, but must prove Fight `exists`, `supports`, and helper semantics. Otherwise reuse Fight Twig/PHP engines. Reset view state in long-running workers. | Choose paths/layout/theme/helpers and request-scope/state-reset policy. |
| Synchronous commands, queries, and events | Yii DI is PSR-11, while Yii event dispatch is PSR-14 ([event dispatcher](https://github.com/yiisoft/event-dispatcher)). Fight buses have explicit routing and event fan-out/failure semantics that PSR-14 does not guarantee. | **Neutral adapter reuse plus providers.** Register Fight `ServiceAwareCommandRouter`, `ServiceAwareQueryRouter`, and `ServiceAwareEventDispatcher` with handler maps. Rename the dependency-free `SymfonyCommandMessageHandler` and `SymfonyEventMessageHandler` to neutral handlers. Do not silently substitute PSR-14 for Fight event semantics. | Declare command/query handler maps and event subscriber order; use PSR-14 separately for Yii lifecycle events. |
| Async commands and events | `yiisoft/queue` separates producers/consumers, provides synchronous and asynchronous producers, JSON message serialization, console run/listen commands, and official AMQP/Redis adapters ([queue](https://github.com/yiisoft/queue), [queue README](https://github.com/yiisoft/queue/blob/master/README.md), [adapter list](https://github.com/yiisoft/queue/blob/master/docs/guide/en/adapter-list.md)). It has no stable release and is `3.0.x-dev`. | **Prototype only; release-gated reusable adapter.** A queued Fight command/event wrapper can carry a stable type plus the Fight serializer payload. Queue handlers deserialize into `CommandMessage`/`EventMessage` and delegate to the neutral synchronous handlers. Do not publish it as stable or require it from Fight Common until core and broker tags exist. | During experimentation, explicitly pin dev packages and isolate them in the Yii starter. After release, choose broker/exchange/queue, routing keys, acknowledgement, and failure policy. |
| Serialization | Yii's former general serializer package is archived ([archived serializer](https://github.com/yiisoft/serializer)). Queue has its own transport message interface and JSON encoder ([queue message](https://github.com/yiisoft/queue/blob/master/src/Message/MessageInterface.php), [queue serializer](https://github.com/yiisoft/queue/tree/master/src/Message/Serializer)). | **Neutral reuse.** Keep Fight Domain `JsonSerializer`/`PhpSerializer` as the canonical command/event payload codec. A future Yii Queue adapter composes it inside the queue transport serializer; it does not replace it or introduce a general `YiiSerializer`. | Version message types, secure class resolution, and plan schema/upcaster evolution. Never deserialize arbitrary classes from untrusted metadata. |
| Retries and failures | Queue's failure middleware includes send-again and exponential-delay behavior with attempt metadata; delayed retry depends on transport support ([error handling](https://github.com/yiisoft/queue/blob/master/docs/guide/en/error-handling.md)). | **Queue adapter configuration, release-gated.** Preserve Fight handler failures so the queue can negatively acknowledge/retry. Retry policy is not part of the synchronous bus and must not be hidden in the neutral handler. | Set retryable exceptions, attempts/backoff/jitter, dead-letter/failure storage, poison-message handling, and alerting; verify chosen adapter supports delay. |
| Worker lifecycle | Queue provides `queue:run`, `queue:listen`, and `queue:listen-all`; workers are long-running and `ext-pcntl` is recommended for signals ([queue README](https://github.com/yiisoft/queue/blob/master/README.md), [worker guide](https://github.com/yiisoft/queue/blob/master/docs/guide/en/worker.md)). | **Starter-only operations.** Fight Common may supply stateless handlers and provider definitions, but not supervisor units or deployment policy. Long-lived services must clear mutable view/logger/container state as their packages require. | Configure Supervisor/systemd, graceful termination, max jobs/memory/time, restarts, deployment drains, health, logging flush, DB reconnect, and metrics. |

## Package and compatibility boundary

The official application manifests are the best current compatibility baseline because Yii 3 has no
single framework version. As of the research date the web application supports PHP `8.2 - 8.5` and
uses stable constraints including Config `^1.6.2`, DI `^1.4.1`, Log `^2.2.0`, Middleware Dispatcher
`^5.4`, Router `^4.0.2`, View `^12.2.4`, Yii HTTP `^1.1.1`, and Yii View Renderer `^7.4.1`
([web app manifest](https://github.com/yiisoft/app/blob/master/composer.json)); the API application adds
the stable PSR-7 data-response composition
([API app manifest](https://github.com/yiisoft/app-api/blob/master/composer.json)). Yii's package policy
uses semantic versioning and distinguishes general-purpose `yiisoft/*` packages from Yii-specific
`yiisoft/yii-*` packages ([package policy](https://yiisoft.github.io/docs/internals/000-packages.html)).

Optional adapters must therefore use `suggest`/dev-fixture constraints or split packages rather than add
all Yii packages to Fight Common's production `require`. A combined latest/lowest dependency fixture must
prove that each provider loads without unrelated optional packages. The queue package is outside that
stable matrix until tagged; a passing prototype against `dev-master` proves only the seam, not a supported
version.

## Recommended implementation order

1. Land the namespace move to `Adapter\ServiceContainer\Yii` and add a minimal common provider/config
   convention with loadability tests.
2. After T-00059 lands, implement `YiiDbTransactionalUnitOfWork` against the shared transaction
   conformance suite.
3. Implement `YiiUrlGenerator` and the neutral PSR-7 JSend adapter; wire both from separate providers.
4. Conformance-test Yii Mailer and Yii View mappings before accepting those adapters. A mapping that drops
   attachment, priority, helper, or missing-template behavior is not sufficient.
5. Rename the dependency-free command/event message listeners to neutral handlers and wire the synchronous
   Fight buses through Yii DI.
6. Keep Queue behind an explicit experimental fixture. Re-evaluate when `yiisoft/queue` and at least one
   production broker adapter publish stable compatible releases; then run serialization, retry,
   acknowledgement, failure, signal, and long-running-state tests before claiming support.

This yields easy starter wiring without turning Fight Common into a Yii distribution: Fight Common owns
portable contracts, verified bridges, and opt-in registration; each starter owns application policy and
operations.
