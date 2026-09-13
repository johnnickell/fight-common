# WF-022: CodeIgniter 4 native adapter seams

**Researched:** 2026-08-22
**Scope:** CodeIgniter 4.7, its official packages, and the current Fight Common public ports and adapters
**Evidence rule:** Only official CodeIgniter documentation, official CodeIgniter repositories/packages, and live Fight Common source were used. No community package is selected by this research.

## Finding

CodeIgniter can support the important Fight Common seams, but the right result is not a CodeIgniter wrapper for every port. There are four distinct integration shapes:

1. **Reusable Fight Common adapter:** required where CodeIgniter has useful native behavior with a materially different API. The strongest candidates are database transactions, Queue-backed command/event delivery, JSend responses and exception handling, URL generation, and mail transport.
2. **Service-container factory:** capability-scoped factories should live under `Adapter\ServiceContainer\CodeIgniter`, while a starter application's `app/Config/Services.php` explicitly delegates only the capabilities it enables.
3. **Neutral adapter reuse:** the existing PSR, Guzzle, Flysystem, Symfony Process component, Twilio, Mercure, PHP/Twig, serializer, and synchronous messaging adapters already cover many seams better than a CodeIgniter-specific wrapper would.
4. **Starter-only configuration:** routes, filters, Queue job aliases and backends, Tasks schedules, migrations, worker supervision, credentials, and domain repository mappings belong to the consuming skeleton.

The official `codeigniter4/queue` package is now stable. Its first stable release was 1.0.0 on 2026-03-29 and 1.0.1 followed on 2026-07-24, so earlier research that treated Queue as pre-stable is obsolete. The official Tasks package is also stable and now depends on Queue. ([Queue releases](https://github.com/codeigniter4/queue/releases), [Tasks package](https://packagist.org/packages/codeigniter4/tasks), [official packages](https://codeigniter.com/user_guide/libraries/official_packages.html))

## Placement model

The names below are candidates to prototype, not accepted API names.

| Placement | Responsibility |
|---|---|
| `Adapter\ServiceContainer\CodeIgniter\<Capability>Services` | Small reusable factory/delegate for one opt-in capability |
| Starter `app/Config/Services.php` | Native discovery surface that delegates to enabled capability factories |
| `Adapter\Persistence\CodeIgniter` | Native transaction/UoW adapter only; domain repositories stay consumer-owned |
| `Adapter\Messaging\CodeIgniter` | Queue producers and Queue job consumers |
| `Adapter\Http\CodeIgniter` | Native response/JSend and exception-handler integration |
| `Adapter\Middleware\CodeIgniter` | Only shared filters whose behavior is genuinely portable; alias and route registration remain starter-owned |

CodeIgniter service discovery looks for a namespace-root `Config/Services.php` extending `BaseService`. A class located only at `Fight\Common\Adapter\ServiceContainer\CodeIgniter\CommandBusServices` is therefore not itself a conventionally discovered service provider. The least surprising integration is an explicit starter `Config\Services` method delegating to Fight Common. This also preserves capability-level opt-in and avoids installing every integration for every consumer. ([Services documentation](https://codeigniter.com/user_guide/concepts/services.html), [framework `Services` source](https://github.com/codeigniter4/CodeIgniter4/blob/develop/system/Config/Services.php), [`BaseService` source](https://github.com/codeigniter4/CodeIgniter4/blob/develop/system/Config/BaseService.php))

## Full capability sweep

| Capability | Native CodeIgniter seam | Recommended Fight Common disposition | Ownership |
|---|---|---|---|
| Service container / dependency injection | `Config\Services` shared factories and service discovery | Add capability-scoped CodeIgniter factory delegates; expose them through starter `app/Config/Services.php` | Reusable factory plus starter registration |
| Authentication / authorization | Official Shield supplies session, token, HMAC, JWT, groups and permissions | Do not force Shield through the present PSR-7 `Authenticator` contract: CodeIgniter explicitly does not use PSR-7 HTTP messages. Use Shield natively for application identity/filters, or wire existing neutral Fight authentication services where their contracts fit. Add a shared Shield adapter only after a framework-neutral principal contract exists. | Starter configuration; no adapter yet ([Shield](https://shield.codeigniter.com/), [authentication](https://shield.codeigniter.com/latest/references/authentication/authentication/), [PSR support](https://codeigniter.com/user_guide/intro/psr.html)) |
| Cache | Core cache handlers; official `codeigniter4/cache` exposes PSR-6 `Pool` and PSR-16 `SimpleCache` | Feed the official PSR-6 pool to existing `PsrCache`; no CodeIgniter cache wrapper | Neutral adapter plus service factory ([cache docs](https://codeigniter.com/user_guide/libraries/caching.html), [official cache package](https://github.com/codeigniter4/cache)) |
| Domain repositories | Model and Query Builder | Aggregate hydration, identity, and write rules are domain-specific; keep repository implementations in the skeleton/application | Starter only ([models](https://codeigniter.com/user_guide/models/model.html)) |
| Unit of Work / transactions | Database transaction API supports automatic/manual transactions and status checks | Prototype `CodeIgniterTransactionalUnitOfWork` against the additive `TransactionalUnitOfWork` port: explicitly begin, invoke callback, verify status, commit or roll back, reject nesting, and become terminal after close. Do not implement deprecated legacy `commit()` merely to mimic Doctrine. CodeIgniter's nesting semantics and disabled-by-default transaction exceptions make explicit checks essential. | Reusable adapter ([transactions](https://codeigniter.com/user_guide/database/transactions.html)) |
| Event store / projections | Database layer only; no native event-store abstraction | Continue using neutral DBAL/in-memory event-store and projection adapters. Queue jobs may trigger projectors, but do not change event-store semantics. | Neutral reuse plus starter wiring |
| File storage | File/SPL helpers and writable application directory | Native facilities do not provide the remote/storage abstraction required by `FileStorage`; retain `FlysystemStorage` | Neutral adapter plus service factory ([File collection](https://codeigniter.com/user_guide/libraries/files.html), [filesystem helper](https://codeigniter.com/user_guide/helpers/filesystem_helper.html)) |
| Filesystem | File/SPL helpers | They do not cover the full Fight filesystem contract (including broad copy/mirror/link operations); retain the standalone Symfony Filesystem component adapter | Neutral adapter plus service factory |
| File transfer | No official general transfer abstraction identified in the official package catalog | Retain existing neutral transfer adapters; credentials and endpoints belong in the starter | Neutral adapter plus starter configuration |
| HTTP client | `CURLRequest` is synchronous and uses CodeIgniter HTTP messages | It cannot naturally satisfy the PSR-7 plus asynchronous Fight `HttpClient` contract; retain `GuzzleHttpClient` | Neutral adapter plus service factory ([CURLRequest](https://codeigniter.com/user_guide/libraries/curlrequest.html), [PSR support](https://codeigniter.com/user_guide/intro/psr.html)) |
| Inbound request | CodeIgniter request objects, not PSR-7 | Controllers may translate at the application edge; do not add an incomplete general request bridge without a declared application port | Starter edge |
| Response / JSend | `ResponseTrait`, `respond()`, `setJSON()`, and native `ResponseInterface` | Prototype a native JSend response factory/adapter under `Adapter\Http\CodeIgniter`; do not subclass the Symfony JSend response | Reusable adapter ([API responses](https://codeigniter.com/user_guide/outgoing/api_responses.html), [responses](https://codeigniter.com/user_guide/outgoing/response.html)) |
| Error responses | Configurable `ExceptionHandlerInterface` | Prototype a JSend exception handler that translates only declared exceptions and delegates/retains safe production behavior for the rest; select it in starter `Config\Exceptions` | Reusable adapter plus starter selection ([error handling](https://codeigniter.com/user_guide/general/errors.html)) |
| Middleware / filters | Before/after filters registered by aliases, globals, methods, or routes | CodeIgniter filters are not PSR-15. Add a shared native filter only when Fight Common owns reusable behavior; keep aliases and route assignments in `Config\Filters`/routes. Existing Symfony middleware moves under its Symfony namespace and is not inherited by CodeIgniter. | Conditional reusable adapter plus starter registration ([filters](https://codeigniter.com/user_guide/incoming/filters.html)) |
| Validation | Native validation service/rules | Domain/application validation remains framework-neutral; use native validation only at the controller/form boundary | Starter edge |
| URL generation / routing | Named routes and `url_to()` | Prototype `CodeIgniterUrlGenerator`, translating named-route parameters and appending encoded query parameters. Conformance tests must pin relative versus absolute output and missing-route failures. | Reusable adapter ([URL helper](https://codeigniter.com/user_guide/helpers/url_helper.html), [routing](https://codeigniter.com/user_guide/incoming/routing.html)) |
| Mail | Native Email supports mail/sendmail/SMTP, text/HTML alternatives, attachments, inline content, priority, CC/BCC | Prototype `CodeIgniterMailTransport` using the neutral Fight `MailMessage`. Define fail-closed behavior for unsupported multiple From/Reply-To values and any unmapped timestamp/line-length behavior before accepting it. | Reusable adapter plus service factory ([Email library](https://codeigniter.com/user_guide/libraries/email.html), [`Email` source](https://github.com/codeigniter4/CodeIgniter4/blob/develop/system/Email/Email.php)) |
| SMS | No official SMS package is listed | Retain `TwilioSmsTransport`; do not select an unevaluated community package | Neutral adapter plus starter credentials |
| Socket / realtime publication | No official WebSocket/realtime publication package is listed | Retain Mercure publisher adapters (or add another standalone provider through the existing port in a separate ticket) | Neutral adapter plus starter hub/topic policy |
| Templating | Native PHP view renderer, layouts, and view cells | Default to the existing neutral `PhpEngine` or Twig adapter. A native `CodeIgniterTemplateEngine` is justified only if a skeleton needs native view discovery/layout behavior and conformance proves `supports()` and helper semantics. | Neutral reuse; conditional adapter ([view renderer](https://codeigniter.com/user_guide/outgoing/view_renderer.html)) |
| Logging / audit | Native logger is PSR-3 and replaceable through Services | Inject it into existing `LoggingAuditLog`; no CodeIgniter audit adapter | Neutral adapter plus service factory ([logging](https://codeigniter.com/user_guide/general/logging.html), [PSR support](https://codeigniter.com/user_guide/intro/psr.html)) |
| Metrics | Timer/benchmark service, not a counter/gauge/histogram backend | Retain StatsD or null metrics adapters; Queue lifecycle listeners may record metrics through the neutral port | Neutral adapter plus optional starter listeners ([benchmarking](https://codeigniter.com/user_guide/testing/benchmark.html), [Queue events](https://queue.codeigniter.com/1.0/events/)) |
| Health checks | No official general health-check package is listed | Retain `HealthReporter` and existing database/HTTP checks | Neutral reuse plus starter checks |
| Process management | CLI commands/signals and experimental FrankenPHP worker mode are lifecycle facilities, not arbitrary subprocess execution | Retain `SymfonyProcessRunner`; do not confuse an HTTP/Queue worker with the `ProcessRunner` contract | Neutral adapter plus service factory ([CLI signals](https://codeigniter.com/user_guide/cli/cli_signals.html), [worker mode](https://codeigniter.com/user_guide/installation/worker_mode.html)) |
| Synchronous commands / queries / events | Core Events is a static string-keyed listener system that may stop propagation on `false` | Retain Fight's typed synchronous routers, command/query buses, and event dispatcher. Wire explicit handler maps through Services; reserve CodeIgniter Events for framework/package lifecycle observation. | Neutral messaging plus service factory ([Events](https://codeigniter.com/user_guide/extending/events.html)) |
| Async commands / events | Official Queue package | Add Queue-backed command bus/event dispatcher producers and typed Queue jobs that hand reconstructed messages to the synchronous Fight buses | Reusable adapter plus starter Queue config ([Queue basic usage](https://queue.codeigniter.com/1.0/basic-usage/)) |
| Serialization | Queue JSON-encodes data and requires queued objects to be `JsonSerializable` | Use Fight `JsonSerializer` explicitly and put a serialized string plus message kind in Queue data. Do not reuse `SymfonyMessageSerializer`, and do not rely on automatic object rehydration. | Neutral serializer inside reusable jobs ([Queue basic usage](https://queue.codeigniter.com/1.0/basic-usage/)) |
| Retries / failed jobs | Queue job/worker retry limits, retry delay, failed-job retention, and retry/forget/flush commands | Let handler exceptions escape the Queue job so native retry/failure accounting remains authoritative. Document idempotency and configure limits in the starter. | Official package plus starter policy ([Queue configuration](https://queue.codeigniter.com/1.0/configuration/), [Queue commands](https://queue.codeigniter.com/1.0/commands/)) |
| Worker lifecycle | Queue safe stop, job/time/memory limits, lifecycle events, and Supervisor/cron guidance | Starter owns process supervisor, queue names, deployment restart, and operational policy; reusable event listeners may bridge lifecycle signals to neutral audit/metrics ports | Starter operations ([running queues](https://queue.codeigniter.com/1.0/running-queues/), [Queue events](https://queue.codeigniter.com/1.0/events/), [troubleshooting](https://queue.codeigniter.com/1.0/troubleshooting/)) |
| Scheduling | Official Tasks supports commands, closures, URLs, events, Queue jobs, cron frequencies, environments, and single-instance locks | Keep native task declarations in starter `app/Config/Tasks.php`. Fight's current Scheduler is a concrete application service rather than a provider port, so there is no honest Tasks adapter target yet. | Starter configuration ([Tasks usage](https://tasks.codeigniter.com/latest/basic-usage/), [Tasks configuration](https://tasks.codeigniter.com/latest/configuration/)) |

“No official package is listed” above is a bounded inference from CodeIgniter's current [official package catalog](https://codeigniter.com/user_guide/libraries/official_packages.html), not a claim that no third-party implementation exists. Community packages were outside this primary-source pass and none is needed for the recommended baseline.

## Queue adapter shape

The official Queue package supports database, Redis/Predis, and RabbitMQ handlers; priority, delay and chains; retry/failure retention; multiple-worker safety; and worker lifecycle commands. Its `BaseJob` is constructed from an array and exposes `process()` rather than constructor injection. A reusable integration therefore needs a deliberately small service-locator boundary inside the job, while producers can use normal constructor injection. ([Queue configuration](https://queue.codeigniter.com/1.0/configuration/), [`BaseJob` source](https://github.com/codeigniter4/queue/blob/develop/src/BaseJob.php))

Candidate components:

- `QueueCommandBus`: accepts a Fight command message, serializes it with the neutral domain serializer, pushes the stable command-job alias, and throws when the Queue push result is unsuccessful.
- `QueueEventDispatcher`: does the equivalent for event messages.
- `CommandMessageJob` and `EventMessageJob`: deserialize and type-check the payload, resolve one exact named synchronous Fight service from `service()`, and invoke the generic command/event message handler.
- The current Symfony-named command/event message listeners do not import Symfony. Their generic deserialize/dispatch role should be renamed and reused rather than copied into CodeIgniter.

The starter owns `Config\Queue::$jobHandlers`, queue/backend selection, database migrations, worker commands/Supervisor configuration, and the exact service names resolved by the jobs. Exceptions from deserialization or the synchronous handler must escape `process()` so Queue performs retries and failed-job recording. Message handlers must consequently be idempotent.

Queue dispatch is not an atomic outbox. The application must enqueue only after a successful transaction; applications requiring atomic persistence-and-publication still need an outbox/event-store delivery design. A CodeIgniter Queue adapter must not imply a guarantee the package cannot provide.

## Prototype gates

Before promoting candidate names into an implementation ticket, prove these behaviors against a fixture requiring `codeigniter4/framework:^4.7` and `codeigniter4/queue:^1.0`:

1. A starter `Config\Services` can delegate each capability independently without service-discovery collisions.
2. The transaction adapter rejects nesting, detects a failed transaction even when database exceptions are disabled, rolls back on every throwable, and reports terminal closure correctly.
3. Queue command and event round trips preserve concrete message type and metadata, propagate handler failure, retry, enter failed storage, and can be retried successfully.
4. A failed Queue push is visible to the caller, and no enqueue happens before transaction success.
5. JSend response and exception adapters preserve status, headers, and production-safe error disclosure.
6. URL generation matches the Fight port for parameters, query strings, relative/absolute output, and unknown routes.
7. Mail conformance either maps every required field or rejects unsupported multiplicity explicitly.

## Recommended ticket split

1. CodeIgniter service-factory fixture and capability opt-in.
2. CodeIgniter transactional Unit of Work after the additive transaction port lands.
3. CodeIgniter Queue command/event producers, jobs, generic listener rename, and failure/retry tests.
4. CodeIgniter HTTP/JSend response and exception integration.
5. CodeIgniter URL generator.
6. CodeIgniter mail transport conformance prototype.
7. Starter wiring for Shield, routes/filters, Queue migrations/workers, Tasks schedules, and the existing neutral adapters.

This ordering establishes composition, transaction, and asynchronous-delivery seams first while avoiding provider wrappers that add no capability.
