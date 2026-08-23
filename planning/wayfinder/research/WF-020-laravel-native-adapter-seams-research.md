# WF-020 Laravel-native adapter seams research

**Date:** 2026-08-22
**Ticket:** [Research Laravel-native adapter seams](../tickets/WF-020-laravel-native-adapter-seams.md)
**Primary line researched:** Laravel 13.x

## Executive finding

Laravel has first-party seams for nearly every capability in the requested sweep, but that does not imply one
Fight-branded runtime adapter per Laravel facade. The useful package boundary is:

1. publish Laravel runtime adapters when an Illuminate contract must be translated to a Fight Application
   port;
2. publish small, capability-specific service providers under `Adapter\ServiceContainer\Laravel` to wire one
   adapter or neutral implementation at a time;
3. reuse provider-neutral Fight adapters when Laravel already exposes the same underlying standard or library;
   and
4. leave credentials, disks, queue connections, workers, routes, views, middleware ordering, and operational
   packages in starter configuration.

The highest-value new implementation is native async messaging. A Laravel queued command job and queued event
listener can carry Fight `CommandMessage` and `EventMessage` envelopes and delegate consumption to the neutral
`CommandMessageHandler` and `EventMessageHandler`. Laravel's after-commit scheduling prevents workers from seeing
rolled-back or uncommitted records, but it is not an atomic outbox and cannot close the crash window between a
database commit and a queue push.

The second high-value implementation is a Laravel `TransactionalUnitOfWork` around one Illuminate database
connection. It should implement the narrower boundary from T-00059, not fabricate the legacy standalone
`commit()` operation that exists for Doctrine's persistence context.

## Package and service-container boundary

Laravel service providers are the native package-to-container connection point. `register()` is for bindings;
event listeners, middleware, routes, and other boot work belong in `boot()`. Providers that only bind services
may implement `DeferrableProvider`. Laravel package discovery can register providers listed in Composer's
`extra.laravel.providers`, while an application may opt out of discovery.
[Service providers](https://laravel.com/docs/13.x/providers) and
[package discovery](https://laravel.com/docs/13.x/packages#package-discovery)

ADR 0023's split-provider decision fits those rules:

```text
Adapter\ServiceContainer\Laravel\CommandBusServiceProvider
Adapter\ServiceContainer\Laravel\EventBusServiceProvider
Adapter\ServiceContainer\Laravel\PersistenceServiceProvider
Adapter\ServiceContainer\Laravel\CacheServiceProvider
Adapter\ServiceContainer\Laravel\FileStorageServiceProvider
Adapter\ServiceContainer\Laravel\MailServiceProvider
Adapter\ServiceContainer\Laravel\RoutingServiceProvider
Adapter\ServiceContainer\Laravel\TemplatingServiceProvider
```

Do not auto-discover every provider. Composer discovery of all capability providers would silently wire optional
features merely because Fight Common was installed. The Laravel starter should list only the providers it uses
in `bootstrap/providers.php`; alternatively a consuming package may list a deliberately selected provider in its
own discovery metadata. A binding-only provider may be deferred, but an event provider that registers the queued
event listener is boot work and should not pretend to be binding-only.

Fight Common should keep Illuminate dependencies optional: development constraints for conformance testing and
Composer `suggest` entries for consumers. Each adapter must remain autoload-safe when its Illuminate package is
absent. Laravel's package documentation identifies `illuminate/support` as the dependency for service providers;
runtime adapters should additionally constrain only the narrow Illuminate components they actually import.
[Package service providers](https://laravel.com/docs/13.x/packages#service-providers)

## Capability disposition

| Capability | Disposition | Reusable Fight Common surface | Service provider responsibility | Starter-only responsibility |
| --- | --- | --- | --- | --- |
| Service container | **Build** | Split Laravel providers; bind Fight ports to selected adapters and tag ordered handlers/filters | One provider per bounded capability | Select providers and project-owned handlers/repositories |
| Async commands | **Build first** | `Adapter\Messaging\Laravel\LaravelCommandBus` plus a queueable `CommandMessageJob` | Bind async bus and neutral consumer handler | Queue driver, connection, queue, retries, timeout, failed-job storage, workers |
| Async events | **Build first** | `Adapter\Messaging\Laravel\LaravelEventDispatcher` plus `EventMessageListener` | Register exactly the generic queued listener and bind the neutral event consumer | Queue operations and any project event listeners |
| UnitOfWork | **Build first** | `Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork` | Bind a selected `ConnectionInterface` to `TransactionalUnitOfWork` | Connection name, isolation level, schema, repository implementations |
| Persistence | **Selective** | Reusable Eloquent casts for stable Fight value types are candidates | Register casts only where Laravel offers a global seam; otherwise none | Aggregate repositories, models, tables, scopes, migrations |
| Authentication | **Selective** | Laravel password hasher/validator adapters are exact; request authentication is not | Bind Fight hashing ports to Illuminate `Hasher` when selected | Guards, providers, Sanctum/Passport/Fortify, sessions, auth routes and principal mapping |
| Cache | **Build or wire PSR** | Small adapter over Illuminate cache `Repository::remember`; keep `PsrCache` for a PSR-6 pool | Bind the selected store-backed adapter | Store driver, prefix, lock backend, eviction operations |
| File storage | **Prefer neutral reuse** | Existing `FlysystemStorage` can wrap Laravel's underlying Flysystem operator; a Laravel wrapper around the filesystem contract is useful only to preserve Laravel fakes/disk selection | Resolve a named disk and bind `FileStorage` | Disk names, roots, credentials, visibility, temporary URL policy |
| Local filesystem | **Build only if conformance passes** | Adapter over `Illuminate\Filesystem\Filesystem`; existing Symfony adapter remains valid | Bind selected filesystem | Application paths and permissions |
| HTTP client | **Prefer Guzzle adapter** | Existing Guzzle/PSR adapter already matches the Fight PSR request/response and async contract | Wire Guzzle adapter, or a later Illuminate adapter if Laravel fake/event integration justifies translation | Base URLs, credentials, timeouts, retry policy |
| HTTP/JSend | **Build** | `Adapter\Http\Laravel\JSendResponse` and `Adapter\Http\Laravel\Controller\ErrorController` around the neutral JSend envelope | Bind response/controller collaborators only | Exception-render registration, status policy, route/controller selection |
| Middleware | **Build selectively** | Laravel validation/auth/correlation middleware only where Fight owns reusable behavior | Bind constructor dependencies | Global/group/alias ordering in `bootstrap/app.php` |
| Logging | **Neutral reuse** | PSR-3 logging and existing logging decorators | Bind Laravel's PSR logger into Fight decorators | Channels, stacks, Monolog handlers, processors and retention |
| Metrics/health/audit | **Mostly neutral** | Existing metrics, health, and audit ports/adapters; a Pulse adapter is optional and not the default metrics backend | Wire selected collectors/checks | Telescope, Pulse and dashboard access, sampling, storage and daemons |
| Process management | **Defer** | Existing Symfony Process runner is already usable; Laravel wrapper only if its fake/pool semantics prove equal | Wire existing runner | Host supervision and deployment restarts |
| URL generation | **Build** | `Adapter\Routing\Laravel\LaravelUrlGenerator` over Laravel's URL generator | Bind the routing port | Route declarations, trusted hosts/proxies, forced origin/scheme |
| Mail | **Build** | `Adapter\Mail\Laravel\LaravelMailTransport` and a reusable mailable translating Fight `MailMessage` | Bind selected Laravel mailer/factory | Mailer transports, credentials, from defaults, queue choice |
| SMS | **Keep provider adapters** | Existing Twilio transport remains the exact Fight `SmsMessage` mapping | Wire Twilio or another explicit provider | Credentials and notification-channel selection |
| Socket/broadcast | **Build** | `Adapter\Socket\Laravel\LaravelBroadcastPublisher` over a configured broadcaster with a documented fixed event/payload mapping | Bind broadcaster and configured event name | Reverb/Pusher/Ably choice, channels, authorization and server process |
| Templating | **Build** | `Adapter\Templating\Laravel\BladeEngine` over the view factory | Bind view factory and registered Fight helpers | View paths, namespaces, components and project templates |

## Async commands: Laravel Job to synchronous Fight bus

Laravel's bus dispatcher queues any job implementing `ShouldQueue`; a worker invokes its `handle` method and
container-injects dependencies. Queueable jobs can select a connection and queue and can request after-commit
dispatch. Laravel exposes retry count, backoff, timeout, job middleware, encryption, uniqueness, failure hooks,
failed-job storage, worker events, and queue fakes. [Queues](https://laravel.com/docs/13.x/queues),
[bus dispatcher API](https://api.laravel.com/docs/13.x/Illuminate/Contracts/Bus/Dispatcher.html), and
[Queueable API](https://api.laravel.com/docs/13.x/Illuminate/Bus/Queueable.html)

Recommended package shape:

```text
LaravelCommandBus implements AsynchronousCommandBus
  execute(Command) -> dispatch(CommandMessage::create(command))
  dispatch(CommandMessage) -> Illuminate Bus Dispatcher dispatch(CommandMessageJob)

CommandMessageJob implements ShouldQueue
  contains one CommandMessage
  handle(CommandMessageHandler) -> neutral handler -> SynchronousCommandBus::dispatch(message)
```

`CommandMessageJob` should use Laravel's queue traits only for transport attributes and job interaction; domain
handling remains in the neutral Fight handler. The bus constructor may accept default connection, queue, and an
after-commit flag, but environment-specific values come from starter configuration through
`CommandBusServiceProvider`.

Use after-commit by default for the Fight async bus. Laravel documents that jobs dispatched inside an open
transaction can otherwise run before the records they need are committed. With a queue connection's
`after_commit` option enabled, or with the job's `afterCommit()` option, Laravel defers the push until all open
parent transactions commit and discards the deferred job when the transaction rolls back.
[Jobs and database transactions](https://laravel.com/docs/13.x/queues#jobs-and-database-transactions)

Do not use `dispatchAfterResponse()` as an async durability mechanism. It delays execution until after the HTTP
response in the same PHP process; it is not a queue guarantee. Do not make `ShouldBeUnique` the default either.
Laravel uniqueness is a cache lock requiring a shared supported cache backend; it is not exactly-once delivery.
The Fight `MessageId` is the correct idempotency identity, and consumers should durably reject duplicate effects.
[Unique jobs](https://laravel.com/docs/13.x/queues#unique-jobs)

## Async events: queued Laravel listener to synchronous Fight dispatcher

The user's remembered listener design is supported directly by Laravel. Laravel's event dispatcher queues a
listener that implements `ShouldQueue`, and `ShouldQueueAfterCommit` forces that listener to be submitted only
after open database transactions commit even when the queue connection's global option is false.
[Queued event listeners](https://laravel.com/docs/13.x/events#queued-event-listeners) and
[queued listeners after commit](https://laravel.com/docs/13.x/events#queued-event-listeners-and-database-transactions)

Recommended package shape:

```text
LaravelEventDispatcher implements AsynchronousEventDispatcher
  trigger(Event) -> dispatch(EventMessage::create(event))
  dispatch(EventMessage) -> Illuminate Events Dispatcher::dispatch(message)

EventMessageListener implements ShouldQueueAfterCommit
  constructor(EventMessageHandler) -> container-injected neutral handler
  handle(EventMessage) -> neutral handler
  neutral handler -> SynchronousEventDispatcher::dispatch(message)
```

`EventBusServiceProvider::boot()` registers the one listener for `EventMessage::class`. As with the current
Messenger async dispatcher, registration and handler-introspection methods on the asynchronous Fight dispatcher
remain no-ops because subscribers live behind the synchronous bus consumed by the worker.

One queued event listener deliberately creates one queue job for the complete synchronous Fight fan-out. This
preserves Fight's event ordering and aggregate failure behavior, but a retry can repeat handlers that succeeded
before another handler failed. Every async event handler must therefore be idempotent by `MessageId`. Per-handler
jobs would change the present Fight dispatcher semantics and should not be introduced by a Laravel adapter alone.

### Serialization, retry, and failure constraints

- Persist the complete Fight message envelope, including original `MessageId`, timestamp, payload type, payload,
  and metadata. Do not reconstruct a fresh message in the worker.
- Prove PHP/Laravel queue round trips for every supported Fight scalar/value-object payload shape. Laravel's
  `SerializesModels` behavior is Eloquent-specific; Fight messages should not rely on model rehydration.
- Reject closures, resources, secrets, and arbitrary service objects in message payloads or metadata. Laravel
  notes that binary job data must be base64 encoded because the queue payload is JSON.
- Preserve thrown failures. Laravel retries a released/failed job according to attempts, backoff, timeout, and
  middleware; after attempts are exhausted it stores the failure when failed-job storage is enabled.
- Treat delivery as at least once. `retry_after` must exceed the worker timeout, and process supervision plus
  `queue:restart`/Horizon termination remains a deployment concern.
- Test a real serializing driver, not only the synchronous driver or `Queue::fake()`. The sync driver bypasses
  the transport boundary where incompatible payloads fail.

Laravel documents failed-job storage, retry commands, worker deployment/restart behavior, timeout versus
`retry_after`, queue events, and testing on the same queue reference.
[Failed jobs](https://laravel.com/docs/13.x/queues#dealing-with-failed-jobs) and
[queue workers](https://laravel.com/docs/13.x/queues#running-the-queue-worker)

### After commit is not an outbox

Laravel's transaction manager executes registered callbacks after the root transaction commits, and queue
after-commit uses that lifecycle. The database mutation and broker push are still two operations. A process
failure after commit but before a successful push can lose the message, while a retry after an ambiguous push can
duplicate it. Fight Common must describe this adapter as post-commit at-least-once submission, not atomic message
publication. Consumers requiring atomic durability need a same-database outbox and a relay.
[Laravel connection `afterCommit`](https://api.laravel.com/docs/13.x/Illuminate/Database/Connection.html) and
[database transaction manager](https://api.laravel.com/docs/13.x/Illuminate/Database/DatabaseTransactionsManager.html)

## UnitOfWork and persistence

Laravel's `ConnectionInterface::transaction()` returns the callback result, commits on success, rolls back and
rethrows on failure, and accepts a retry-attempt count for deadlock/concurrency handling. It also exposes manual
begin/commit/rollback and `transactionLevel()`. Eloquent and the query builder use those connection transactions.
[Database transactions](https://laravel.com/docs/13.x/database#database-transactions) and
[ConnectionInterface API](https://api.laravel.com/docs/13.x/Illuminate/Database/ConnectionInterface.html)

That is an exact implementation seam for T-00059's narrower `TransactionalUnitOfWork`, with these constraints:

- `LaravelTransactionalUnitOfWork` wraps one explicitly selected `ConnectionInterface` and returns the callback
  result from `transaction()`.
- It rejects entry when its own transaction is active or `transactionLevel() > 0`. Laravel can create nested
  savepoints, but the portable Fight contract explicitly rejects nested transactional execution.
- On callback failure it rolls back through Laravel and rethrows the original failure. An ordinary rollback does
  not itself close a Fight unit of work; a Laravel connection normally remains reusable. `isClosed()` must
  describe an actual terminal wrapper/connection failure rather than copying Doctrine's close-on-failure behavior
  or pretending the native connection closed.
- It does not implement legacy `UnitOfWork::commit()`. Eloquent/query-builder writes are issued immediately and
  have no Doctrine-like pending persistence context to flush. Fabricating a standalone commit would recreate the
  false abstraction T-00059 is designed to remove.
- The callback must use the same connection for every protected mutation and required audit write. Cross-store
  changes and queue submission are outside its atomic boundary.

Fight Common should not publish a generic Eloquent repository base. Aggregate construction, query criteria,
pagination semantics, locks, table names, and persistence mappings belong to each consumer. Reusable custom casts
for stable Fight values such as UUIDs and email addresses may be considered under
`Adapter\Persistence\Laravel\Cast`, but each cast needs round-trip and nullability evidence. They are separate
from UnitOfWork delivery.

## Auth and cache

Laravel authentication is organized around guards and user providers and is configured per application. Its
ambient guard authenticates an Illuminate request/session, whereas Fight's current `Authenticator` accepts a
PSR-7 server request. Adapting a guard behind that port would ignore the passed request or require a PSR bridge
and project-specific principal mapping. Do not publish a misleading `LaravelAuthenticator` for the existing
port. Keep guard/provider selection, Sanctum/Passport/Fortify, sessions, routes, and user mapping in the starter.
[Laravel authentication](https://laravel.com/docs/13.x/authentication)

Laravel's `Illuminate\Contracts\Hashing\Hasher` does exactly support `make()` and `check()`. Small
`LaravelPasswordHasher` and `LaravelPasswordValidator` adapters are reusable and preserve application-selected
bcrypt/Argon configuration. Laravel has no matching first-party implementation of Fight's request-signing HMAC
or generic token encoder/decoder ports; retain the existing provider adapters.
[Hasher API](https://api.laravel.com/docs/13.x/Illuminate/Contracts/Hashing/Hasher.html)

Laravel cache repositories expose `remember(key, ttl, callback)`, which maps directly to Fight `Cache::read`.
A `LaravelCache` adapter is worthwhile when a consumer wants Laravel store selection, fakes, events, and atomic
lock ecosystem; otherwise `PsrCache` remains valid when the selected store is exposed as PSR-6. Cache driver,
prefix, TTL policy, shared uniqueness-lock store, and operational eviction belong to the starter.
[Laravel cache](https://laravel.com/docs/13.x/cache)

## Filesystem and file storage

Laravel file storage is its Flysystem abstraction. Its filesystem adapter supports string/resource writes,
reads and read streams, existence, deletion, copy/move, size, last-modified timestamps, and shallow/recursive file
and directory listings—the complete Fight `FileStorage` shape.
[File storage](https://laravel.com/docs/13.x/filesystem) and
[FilesystemAdapter API](https://api.laravel.com/docs/13.x/Illuminate/Filesystem/FilesystemAdapter.html)

Prefer binding the existing `FlysystemStorage` to the selected Laravel disk's underlying Flysystem operator.
Publish a thin Laravel-specific wrapper only if using `Storage::fake()`, Laravel-specific disk decoration, or
on-demand disk selection cannot be tested through that neutral adapter. Disk definitions, S3 credentials,
visibility, scoped/read-only modes, public links, and temporary URL policy remain starter configuration.

Laravel also has a local `Illuminate\Filesystem\Filesystem` with a broad file/directory API, including reads,
writes, moves, links, directory copying, file metadata, permissions, and Symfony Finder-backed listing.
[Illuminate Filesystem API](https://api.laravel.com/docs/13.x/Illuminate/Filesystem/Filesystem.html)
A Laravel implementation of Fight's much larger local `Filesystem` port is acceptable only after an operation-
by-operation conformance spike proves touch/access-time, ownership/group, executability, absolute-path, recursive
chmod, mirror-delete, and PHP include behavior. Until then, Laravel can wire the existing Symfony Filesystem
adapter; Laravel itself already uses several Symfony components.

## HTTP, JSend, and middleware

Fight's outbound HTTP client consumes a PSR-7 request, returns a PSR-7 response, and exposes a Fight async
promise. Laravel's HTTP client is an expressive Guzzle wrapper whose public request API is method/URL/options,
although its response wraps a PSR response and it exposes pools, retries, middleware, events, and fakes.
[Laravel HTTP client](https://laravel.com/docs/13.x/http-client)

The existing Guzzle adapter is therefore the exact neutral implementation and should be the Laravel starter
default. A Laravel-branded adapter would need to translate PSR request bodies/headers/options and Guzzle promises
without semantic loss; build it only if access to `Http::fake()`, macros, or Laravel HTTP events provides enough
consumer value to justify a full conformance suite.

Incoming HTTP is different. Laravel uses `Illuminate\Http\Request` and returns Illuminate/Symfony responses;
it also converts returned PSR-7 responses when the optional PSR bridge implementation is installed.
[Laravel requests and PSR-7](https://laravel.com/docs/13.x/requests#psr-7-requests)
Publish a Laravel-native JSend response that converts the neutral JSend envelope into
`Illuminate\Http\JsonResponse`, plus a Laravel ErrorController returning that response. The starter owns
exception renderer registration and decides which exceptions/messages may be exposed.

Laravel already parses JSON requests, so do not port Symfony `JsonRequestMiddleware`. Reusable middleware may be
published where Fight owns the behavior—for example validation-attribute invocation or correlation-context
propagation—but its registration order, aliases, groups, route attachment, trusted proxies, CORS, and authentication
remain in `bootstrap/app.php`.
[Laravel middleware](https://laravel.com/docs/13.x/middleware) and
[error rendering](https://laravel.com/docs/13.x/errors#rendering-exceptions)

## Mail and SMS

Laravel Mail is powered by Symfony Mailer but adds configured mailers, failover/round-robin selection, mailables,
events, queue integration, localization, and testing fakes. Its public `Mailer` contract sends a mailable, view,
or raw text. Laravel mailables support pre-rendered HTML content and in-memory attachments.
[Laravel Mail](https://laravel.com/docs/13.x/mail),
[Mailer contract](https://api.laravel.com/docs/13.x/Illuminate/Contracts/Mail/Mailer.html),
[mailable content](https://api.laravel.com/docs/13.x/Illuminate/Mail/Mailables/Content.html), and
[attachments](https://api.laravel.com/docs/13.x/Illuminate/Mail/Mailables/Attachment.html)

Build `LaravelMailTransport` around the public mailer contract and a concrete reusable `FightMailMailable` that
translates the complete Fight `MailMessage`: addresses, subject, HTML/text parts, sender, return path, priority,
timestamp, attachments, and inline parts. Do not call Laravel Mailer's protected `sendSymfonyMessage()` method.
A conformance spike must prove both HTML-and-text content and inline attachment identity; if Laravel's public
mailable API cannot preserve a field, compose the already tested Fight-to-Symfony message factory inside the
mailable rather than weakening the Fight contract. Queueing the mail transport is not part of the synchronous
`MailTransport`; applications may queue a command that invokes it.

Laravel Notifications are a higher-level notifiable/channel model and do not directly match Fight's simple
`SmsTransport::send(SmsMessage)`, especially its explicit from number and media URLs. Keep the existing Twilio
adapter or add another provider adapter rather than routing generic Fight SMS through a project-owned Laravel
Notification class. Notification channel packages and notification classes remain starter choices.
[Laravel notifications](https://laravel.com/docs/13.x/notifications)

## Routing, templating, socket, process, and observability

Laravel's URL generator exposes named-route generation with parameters and an absolute flag. Extra parameters
become query-string values. `LaravelUrlGenerator` can implement Fight `UrlGenerator`, carefully merging the
separate Fight route-parameter and query arrays without allowing query values to replace route placeholders.
The starter owns routes, model binding, trusted hosts, forced origins, schemes, and signing keys.
[URL generation](https://laravel.com/docs/13.x/urls) and
[URL generator API](https://api.laravel.com/docs/13.x/Illuminate/Routing/UrlGenerator.html)

Blade's view factory can render a named view and report whether it exists. `BladeEngine` can implement Fight
`TemplateEngine`, treat `.blade.php`/registered view names as supported, and expose Fight helpers through view
shared data while retaining duplicate-helper checks in the adapter. View locations, namespaces, components,
composers, and actual templates are starter-owned.
[Laravel views](https://laravel.com/docs/13.x/views)

Laravel broadcasting supports configured Reverb, Pusher, and Ably drivers. A reusable
`LaravelBroadcastPublisher` can call the selected broadcaster with the Fight topic as the channel, a configured
fixed event name, and `['message' => $message]` as payload. That mapping must be constructor-visible and tested;
Fight's current two-string port cannot express Laravel event names, private/presence authorization, or richer
payloads. The starter owns driver credentials, channel authorization, client subscriptions, and the Reverb
server process. [Broadcasting](https://laravel.com/docs/13.x/broadcasting) and
[Reverb](https://laravel.com/docs/13.x/reverb)

Laravel Process provides command execution, input, environment, paths, timeouts, output callbacks, concurrent
pools, retries, and fakes, but it is itself built on Symfony Process. The existing Fight Symfony runner already
implements queueing, concurrency, callbacks, retry/ignore/throw behavior, and PSR logging. Defer a Laravel wrapper
until a conformance spike proves it adds Laravel fakeability without changing Fight failure and retry semantics.
Host process supervision is never a library adapter.
[Laravel Process](https://laravel.com/docs/13.x/processes)

Laravel logging is Monolog-based and its logger is PSR-3 compatible. Bind it directly into Fight logging,
health, process, and audit decorators; do not add a `LaravelLogger`. Channels, stacks, handlers, processors,
retention, and shared request/job context are starter configuration.
[Laravel logging](https://laravel.com/docs/13.x/logging)

Pulse, Telescope, and Horizon observe framework activity but are not general implementations of Fight
`MetricsCollector`, `AuditLog`, or `HealthCheck`. A later optional Pulse collector could translate Fight
increments/gauges/histograms only if Pulse's record/aggregation model preserves their semantics. For 1.2, retain
the existing StatsD/null metrics, logging/database audit, and health adapters; starters may install first-party
observability dashboards independently. Horizon is specifically Redis-queue monitoring and operations, not a
requirement of the Laravel queue adapter.
[Pulse](https://laravel.com/docs/13.x/pulse),
[Telescope](https://laravel.com/docs/13.x/telescope), and
[Horizon](https://laravel.com/docs/13.x/horizon)

## Recommended delivery slices

1. **Neutral message consumers:** add canonical `CommandMessageHandler` and `EventMessageHandler`, retaining the
   Symfony-named 1.x compatibility paths.
2. **Laravel queue walking slice:** one command bus/job, one event dispatcher/listener, two opt-in providers,
   a real serializing queue integration test, retry/failure evidence, and after-commit/rollback tests.
3. **Transactional persistence slice:** T-00059 plus `LaravelTransactionalUnitOfWork` conformance against a real
   Illuminate connection, including nested rejection and terminal failure state.
4. **Core convenience adapters:** Laravel cache, hashing, URL generation, JSend response/ErrorController, Blade,
   and capability providers.
5. **Configured infrastructure adapters:** Laravel mail transport/mailable and broadcast publisher; prove every
   Fight field rather than accepting a lossy translation.
6. **Reuse receipts:** starter tests wiring Flysystem storage, Guzzle HTTP, Symfony local filesystem/process,
   PSR-3 logging, Twilio SMS, and existing health/metrics/audit adapters through selected Laravel providers.
7. **Deferred candidates:** native local filesystem, Illuminate HTTP client, Pulse metrics, and Laravel Process
   wrappers only after focused conformance prototypes demonstrate value over the neutral implementations.

## Verification obligations

- Install Fight Common into a minimal supported Laravel skeleton with only selected Illuminate optional packages.
- Prove that installing the package alone wires no capability provider.
- Prove each provider can be registered independently and that absent unrelated optional dependencies do not
  break container compilation/bootstrap.
- Exercise queue serialization on a database or Redis driver, not only `sync` or fakes.
- Prove original message identity and metadata survive retry and failed-job round trips.
- Prove command execution reaches exactly one synchronous Fight handler and event execution preserves Fight
  fan-out/failure semantics.
- Prove after-commit work is absent before commit and discarded on rollback; document the outbox gap explicitly.
- Run the T-00059 transaction conformance suite against an Illuminate connection and reject nested entry.
- Use Laravel fakes where they are part of the adapter's value—Mail, Storage, Queue, Event, Process—alongside at
  least one real boundary test for serialization or transport translation.
- Verify every provider binding through the native Laravel container and an installed-package consumer, then run
  Fight Common's full submit gate.

## Decisions unblocked for synthesis

1. Laravel async messaging belongs in Fight Common and should use a queued command Job plus a queued
   `EventMessageListener`, both delegating to neutral Fight handlers and defaulting to after-commit submission.
2. Laravel gets a narrow `TransactionalUnitOfWork`; it does not get an artificial legacy Eloquent `commit()`.
3. Capability providers are opt-in and independently registered; no aggregate provider and no auto-discovery of
   all providers.
4. Build native adapters where Illuminate adds a real contract boundary: transactions, cache, hashing, mail,
   routing, JSend/HTTP responses, Blade, broadcasting, and messaging.
5. Reuse Guzzle, Flysystem, Symfony Filesystem/Process, PSR-3/Monolog, Twilio, StatsD, and other provider-neutral
   adapters instead of duplicating them under `Laravel`.
6. Auth guards, repositories, workers, failed-job tables, broker/disk/mail credentials, routes, middleware order,
   templates, Reverb/Horizon/Pulse/Telescope operations, and deployment supervision remain starter-owned.
