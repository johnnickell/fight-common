# WF-025: PSR Interoperability and Adapter Seams Research

- Date: 2026-08-23
- Question: Which accepted PHP-FIG standards can Fight Common consume directly, implement directly, or bridge
  without changing their semantics?
- Evidence boundary: live Fight Common source, Composer metadata, tests, documentation, accepted ADRs, and the
  official accepted PHP-FIG specifications. Draft, abandoned, and deprecated PSRs are not support targets.

## Result

Fight Common should be standards-first where a PSR is already the honest inward contract, and publish an
adapter only where Fight Common adds a distinct semantic contract. This produces four immediate additions:

1. a provider-neutral PSR-17 JSend response factory under `Adapter\Http\Psr17`;
2. reusable PSR-15 JSON-request and JSend-error middleware under `Adapter\Middleware\Psr15`;
3. a PSR-16 implementation of Fight's read-through cache port under `Adapter\Cache\Psr16` and a canonical
   PSR-6 path for the existing adapter under `Adapter\Cache\Psr6`;
4. a PSR-18 client view over Fight's synchronous `send()` behavior under `Adapter\HttpClient\Psr18`.

PSR-3, PSR-7, and PSR-11 are already valid direct dependencies or implementations. PSR-17 is already a
production dependency but is not actually implemented by the current Fight factory classes. PSR-20 is a
good future direct Application dependency, not a reason to introduce a proprietary clock interface.

PSR-14 is a semantic mismatch with Fight messaging. Fight dispatches an `EventMessage`, has registration on
the dispatcher, executes event-specific and `AllEvents` phases, attempts all matching handlers, and reports
their failures together. PSR-14 dispatches an arbitrary event object, delegates discovery to a listener
provider, returns that same object, supports stoppable propagation, and requires the first thrown error to
stop later listeners. Neither can implement the other without observable information or behavior loss.

The official PHP-FIG index currently lists PSR-1, 3, 4, 6, 7, 11, 12, 13, 14, 15, 16, 17, 18, and 20 as
accepted. PSR-0 and PSR-2 are deprecated; PSR-5, 19, 21, and 22 are drafts and therefore are not support
claims ([PHP-FIG PSR index](https://www.php-fig.org/psr/)).

## Classification Matrix

| PSR | Fight classification | Current evidence | Decision |
| --- | --- | --- | --- |
| PSR-1 Basic Coding Standard | Non-runtime coding standard | Project source uses strict declarations and repository coding gates | Conformance policy only; no runtime class or adapter |
| PSR-3 Logger | Direct inward dependency | `psr/log` is required; logging decorators and services accept `LoggerInterface` | Continue direct use; Monolog and framework loggers need no Fight adapter |
| PSR-4 Autoloading | Non-runtime package standard | Composer maps `Fight\Common\` to `src` and test namespaces separately | Continue Composer conformance; no adapter |
| PSR-6 Cache | Inbound adapter | Existing `Adapter\Cache\PsrCache` composes `CacheItemPoolInterface` to implement Fight `Cache` | Publish canonical `Adapter\Cache\Psr6\Psr6Cache`; preserve old FQCN through 1.x |
| PSR-7 HTTP Messages | Direct inward dependency | Application HTTP client, auth, and health contracts already consume PSR-7 requests, responses, streams, and URIs | Continue direct use; do not wrap PSR-7 messages in Fight-owned equivalents |
| PSR-11 Container | Fight implementation and direct inward dependency | Current `Application\Service\Container` implements `ContainerInterface`; routers consume it | Move additively to `Application\ServiceContainer\Container`; preserve old FQCN through 1.x |
| PSR-12 Extended Coding Style | Non-runtime coding standard | Contributor documentation declares PSR-12 and PHPCS enforces repository style | Conformance policy only; the stricter Fight coding standard may extend it |
| PSR-13 Hypermedia Links | Future capability | `UrlGenerator` returns a route URL string but owns no link relation, attributes, or link collection | Do not claim support or adapt routing; consume PSR-13 directly if a hypermedia presentation capability is added |
| PSR-14 Event Dispatcher | Semantic mismatch | Fight dispatches envelopes, owns subscriber registration, has two phases, and aggregates failures | No Fight/PSR-14 bridge and no PSR-14 support claim for Fight messaging |
| PSR-15 HTTP Handlers | Fight implementation opportunity | Current JSON middleware is Symfony-kernel-specific; no PSR server interfaces are installed | Add provider-neutral middleware under `Adapter\Middleware\Psr15` |
| PSR-16 Simple Cache | Inbound adapter opportunity | Fight `Cache::read()` needs only get/load/set with integer TTL | Add `Adapter\Cache\Psr16\Psr16Cache`; do not pretend Fight `Cache` implements the full PSR-16 API |
| PSR-17 HTTP Factories | Direct dependency plus adapter opportunity | Package is required, but current Fight factories do not implement PSR-17 interfaces | Use PSR-17 directly for JSend and middleware; bridge PSR factories into Fight factories only where semantics are proved |
| PSR-18 HTTP Client | Lossless outbound adapter; inbound mismatch | Fight has synchronous `send()` plus options and mandatory `sendAsync()`; PSR-18 is synchronous only | A Fight client can be exposed as PSR-18; a generic PSR-18 client cannot honestly implement the full Fight port |
| PSR-20 Clock | Future direct inward dependency and implementation opportunity | No `psr/clock` dependency; Domain and Application currently construct time directly | Adopt `ClockInterface` only at eligible Application/Adapter seams; Domain remains PSR-free |

## Detailed Findings

### PSR-1, PSR-4, and PSR-12: package and coding conformance

These standards describe source/package organization rather than runtime capabilities. PSR-4 is already
declared by Composer (`composer.json:71-85`). PSR-12 is named by the contributor documentation, while Fight's
own PHP_CodeSniffer standard may remain stricter. No class under `Application` or `Adapter` should claim to
implement these standards. Compatibility is verified by Composer autoload probes and the repository quality
gate, not an adapter test.

### PSR-3: consume the interface directly

PSR-3 exists so a library can receive one `Psr\Log\LoggerInterface` and write to the application's central
logger without depending on its implementation ([PSR-3](https://www.php-fig.org/psr/psr-3/)). Fight Common
already follows that design in cache, HTTP, mail, SMS, file-transfer, process, audit, and publication-failure
decorators, and requires `psr/log` (`composer.json:12`).

There is no useful `MonologLogger`, `LaravelLogger`, `YiiLogger`, or `CodeIgniterLogger` adapter to add when
the supplied logger already implements PSR-3. Framework service-container integrations may bind a framework's
logger to `LoggerInterface`, but that is wiring under `Adapter\ServiceContainer\<Framework>`, not a runtime
logging adapter. A Fight-specific observability port remains justified only where its semantics exceed
logging, as `AuditLog` and `MetricsCollector` do.

### PSR-6 and PSR-16: inbound adapters to the read-through cache

Fight's `Application\Cache\Cache` is not a general cache store. It provides one read-through operation:
`read(string $key, callable $loader, int $ttl): mixed` (`src/Application/Cache/Cache.php:12-25`). The existing
`Adapter\Cache\PsrCache` retrieves a PSR-6 item, invokes the loader only on a miss, stores its result with the
TTL, and logs the outcome (`src/Adapter/Cache/PsrCache.php:16-49`). That is a valid inbound adapter from a
PSR-6 pool to the narrower Fight capability. PSR-6 intentionally standardizes pools, items, hits, TTL, exact
value preservation, and deferred writes ([PSR-6](https://www.php-fig.org/psr/psr-6/)); Fight does not expose
all of those operations.

Canonical additions:

- `Adapter\Cache\Psr6\Psr6Cache implements Application\Cache\Cache`, preserving the current behavior;
- legacy `Adapter\Cache\PsrCache` remains independently functional and deprecated through 1.x;
- `Adapter\Cache\Psr16\Psr16Cache implements Application\Cache\Cache`, composing
  `Psr\SimpleCache\CacheInterface`.

The PSR-16 adapter can distinguish a miss from a cached `null` by passing a unique sentinel as `get()`'s
default, then invoke the loader and call `set($key, $value, $ttl)`. PSR-16 was explicitly designed as the
simpler common cache surface and to make PSR-6 compatibility straightforward
([PSR-16](https://www.php-fig.org/psr/psr-16/)). Adapter tests must prove cached `null`, loader exceptions,
invalid keys, non-positive TTL behavior, logging, and exception translation.

The reverse direction is not lossless: Fight `Cache` cannot implement PSR-6 or PSR-16 because it has no
explicit set, delete, clear, multiple-key, item, or deferred-write operations. Add `psr/simple-cache` as an
optional adapter dependency (development requirement plus Composer suggestion) unless another supported
production contract imports it directly.

### PSR-7: use the common messages, not wrappers

PSR-7 defines the shared request, response, server-request, stream, URI, and uploaded-file representations
for client and server HTTP ([PSR-7](https://www.php-fig.org/psr/psr-7/)). Fight already requires
`psr/http-message` and exposes those types through its Application HTTP client, authentication, and health
boundaries (`composer.json:11`; `src/Application/HttpClient/Transport/HttpClient.php:9-35`). This is direct
inward dependency, not an Adapter implementation.

Fight should add no `FightRequest` or `FightResponse`. Server-facing reusable code should type against
`ServerRequestInterface` and `ResponseInterface`. Framework-native request/response conversion remains
necessary only for a framework that does not expose PSR-7 at its boundary. Slim and modern Yii can consume
the common PSR lane without branded Fight HTTP message adapters.

### PSR-11: existing implementation, corrected namespace

PSR-11 standardizes `get()` and `has()` and requires a not-found exception when `has()` is false
([PSR-11](https://www.php-fig.org/psr/psr-11/)). `Application\Service\Container` already implements
`ContainerInterface` (`src/Application/Service/Container.php:16-64`), while service-aware routers and event
dispatchers accept the interface directly.

ADR 0023's additive canonical path is correct:

- canonical `Application\ServiceContainer\Container`;
- legacy `Application\Service\Container` remains functional through 1.x;
- framework compiler passes, providers, and factories remain under
  `Adapter\ServiceContainer\<Framework>`.

Fight must not publish adapters that merely wrap another conforming PSR-11 container. Service-provider and
compiler-pass classes add registration behavior and are therefore legitimate framework integrations.

### PSR-13: not equivalent to routing

PSR-13 represents a hypermedia link with a target URI, relationship, and optional attributes, plus link
providers and evolvable providers ([PSR-13](https://www.php-fig.org/psr/psr-13/)). Fight's
`Application\Routing\UrlGenerator` accepts a route name and parameters and returns a string. Translating one
to the other requires the caller to invent the link relationship and attributes, while the reverse direction
loses route identity and parameters.

Therefore PSR-13 is a future presentation/hypermedia capability, not a routing adapter. If a real consumer
journey needs hypermedia links, Application may consume the PSR-13 interfaces directly or add a narrower
presentation coordinator around them. No namespace or dependency should be added before that journey exists.

### PSR-14: deliberately separate from Fight domain-event dispatch

PSR-14 requires a dispatcher to receive an arbitrary object, obtain listeners from a distinct listener
provider, invoke them synchronously, return the same event object, stop when a stoppable event requests it,
and let the first listener throwable block remaining listeners
([PSR-14](https://www.php-fig.org/psr/psr-14/)).

Fight's public contract instead:

- accepts an `Event` through `trigger()` or an `EventMessage` through `dispatch()` and returns `void`;
- owns subscriber registration and handler lookup (`src/Application/Messaging/Event/EventDispatcher.php:14-62`);
- sends the envelope, not the payload object, to handlers;
- runs event-specific handlers and then `AllEvents` handlers;
- catches every handler `Throwable`, continues both phases, then throws one ordered `EventDispatchFailed`
  (`src/Adapter/Messaging/Event/Sync/SimpleEventDispatcher.php:39-50`).

A PSR-14-to-Fight adapter would violate PSR-14 by continuing after a failure and could not return the same
event object through Fight's `void` API. A Fight-to-PSR-14 adapter would lose Fight's all-handler completion,
failure aggregation, metadata envelope, and registration semantics. Wrapping `EventMessage` as the PSR event
does not repair these contradictions.

Decision: do not make a Fight dispatcher implement `Psr\EventDispatcher\EventDispatcherInterface`; do not
claim that a PSR-14 dispatcher implements Fight messaging. A consumer may run an independent PSR-14 extension
dispatcher beside Fight domain messaging, but the two buses remain separate and deliberately named.

### PSR-15: reusable server middleware

PSR-15 defines synchronous server-side `RequestHandlerInterface::handle(ServerRequestInterface)` and
`MiddlewareInterface::process(ServerRequestInterface, RequestHandlerInterface)` returning PSR-7 responses.
It recommends a response factory for middleware that generates responses and an outer error-handling
middleware ([PSR-15](https://www.php-fig.org/psr/psr-15/)).

Canonical additions:

- `Adapter\Middleware\Psr15\JsonRequestMiddleware implements MiddlewareInterface`;
- `Adapter\Middleware\Psr15\JSendErrorMiddleware implements MiddlewareInterface`;
- optionally `Adapter\Http\Psr15\JSendRequestHandler implements RequestHandlerInterface` only when a concrete
  reusable terminal-handler journey is identified.

`JsonRequestMiddleware` can reproduce the useful behavior of the current Symfony kernel adapter: for a
state-changing request with JSON content, decode the PSR-7 stream, call `withParsedBody()`, and delegate the
resulting immutable request. It should not be called a Slim adapter because the same class works in any
PSR-15 dispatcher. The existing Symfony implementation moves to `Adapter\Middleware\Symfony` and retains its
old FQCN compatibility path.

`JSendErrorMiddleware` catches downstream throwables, maps expected versus unexpected failures to the neutral
`Application\Http\JSend\JSendEnvelope`, and asks the PSR-17 JSend response factory for the response. Mapping
must reuse the same exception policy as framework-native error controllers so that PSR-15 does not create a
second semantic API.

Add `psr/http-server-handler` to production requirements when these public reusable adapters ship. It is a
small neutral interface dependency, and a plain Fight Common installation must be able to autoload its
published PSR-15 classes without relying on Slim or Yii to install the interface transitively.

### PSR-17: shared factories and the provider-neutral JSend response

PSR-17 defines factories for every PSR-7 message type and explicitly permits the interfaces to be implemented
together or separately ([PSR-17](https://www.php-fig.org/psr/psr-17/)). Fight already requires
`psr/http-factory` (`composer.json:10`) but no current production class implements a PSR-17 factory interface.
The documentation's claim that the HTTP-client layer depends on PSR-17 factory interfaces is therefore ahead
of the implementation.

The highest-value, lossless addition is:

```text
Adapter\Http\Psr17\JSendResponseFactory
```

It composes `Psr\Http\Message\ResponseFactoryInterface` and
`Psr\Http\Message\StreamFactoryInterface`. Its
`fromEnvelope(JSendEnvelope $envelope, int $statusCode, array $headers = []): ResponseInterface` creates a
response, installs the envelope's already-encoded JSON as a stream, applies caller headers, and sets the JSend
content type. It neither re-encodes nor projects data. This is the shared JSend adapter for Slim, Yii, and any
other PSR-7/17 runtime; framework-branded subclasses or wrappers are unnecessary unless a framework requires
a distinct native return type.

PSR-17 factories can also be composed behind Fight's current factories, but the general bridge needs a
contract repair before it is called lossless:

- Fight `MessageFactory::createResponse()` uses an array as its second argument, while PSR-17 uses a reason
  phrase string, so one class cannot directly implement both signatures
  (`src/Application/HttpClient/Message/MessageFactory.php:42-48`);
- Fight `MessageFactory` additionally owns headers, arbitrary body, and protocol application;
- Fight `StreamFactory::createStream(mixed)` is broader than PSR-17's string method, while resource creation
  is a separate PSR-17 method (`src/Application/HttpClient/Message/StreamFactory.php:13-22`);
- Fight `UriFactory::createUri(string)` does align with PSR-17
  (`src/Application/HttpClient/Message/UriFactory.php:13-20`).

Provisional adapter paths, if retained after contract tests, are
`Adapter\HttpClient\Psr17\Psr17MessageFactory`, `Psr17StreamFactory`, and `Psr17UriFactory`. The message
adapter would compose the separate PSR factories and apply headers, body, protocol, and reason through PSR-7
immutable methods. It must define the supported `mixed` body conversions rather than assuming every PSR-17
implementation accepts Guzzle's conveniences. A later major version should prefer the PSR-17 interfaces
directly where Fight's extra combined factory contract adds no value.

### PSR-18: synchronous outward view, not a complete inbound Fight client

PSR-18 defines only synchronous `sendRequest(RequestInterface): ResponseInterface`. It requires well-formed
4xx and 5xx responses to be returned normally and reserves exceptions for requests that could not be sent or
responses that could not be parsed ([PSR-18](https://www.php-fig.org/psr/psr-18/)).

Fight's `HttpClient` requires both `send(RequestInterface, array $options = [])` and
`sendAsync(...): Promise` (`src/Application/HttpClient/Transport/HttpClient.php:15-35`). Therefore:

- a generic PSR-18 client cannot implement Fight `HttpClient` losslessly: it has no asynchronous operation,
  promise, or portable request-options contract;
- blocking inside `sendAsync()` and returning an already-settled promise would satisfy the PHP signature but
  falsely advertise concurrency and is rejected;
- a Fight client can be exposed through a lossless synchronous PSR-18 view.

Canonical addition:

```text
Adapter\HttpClient\Psr18\Psr18Client implements Psr\Http\Client\ClientInterface
```

It composes Fight `HttpClient`, delegates `sendRequest()` to `send($request)`, returns the response carried by
a Fight `HttpException` instead of throwing it for 4xx/5xx, and translates request/network/transfer failures
to the corresponding PSR-18 exception interfaces. Conformance tests must prove 4xx/5xx return behavior,
request identity on exceptions, network classification, malformed-response handling, and no dependency on
Guzzle-specific options.

If accepting arbitrary PSR-18 clients is more important than preserving Fight async behavior, add a new
synchronous Application boundary or consume `Psr\Http\Client\ClientInterface` directly in the relevant
use case. Do not weaken the existing `HttpClient` contract in 1.2.

### PSR-20: direct clock dependency where the architecture permits it

PSR-20's complete runtime contract is `ClockInterface::now(): DateTimeImmutable`, intended to make temporal
behavior predictable in tests ([PSR-20](https://www.php-fig.org/psr/psr-20/)). Fight currently has no
`psr/clock` requirement, while direct current-time calls exist in Domain, Application, and Adapter code.

ADR 0005 allows neutral PSRs in Application but forbids every PSR in Domain. Adoption must preserve that
boundary:

- Application coordinators whose behavior depends on current time may accept `ClockInterface` directly;
- Adapter code may accept it where deterministic time is useful;
- Domain constructors and named factories receive an explicit `DateTimeImmutable` from Application when
  deterministic time is required, or retain PHP time where the accepted Domain contract deliberately owns
  creation;
- do not create an `Application\Clock\Clock` interface that merely duplicates PSR-20.

A concrete implementation is optional. If Fight needs to provide one for its portable standalone stack,
`Adapter\Clock\System\SystemClock implements ClockInterface` is the honest namespace. Otherwise consumers
may bind any conforming clock. `psr/clock` becomes a production requirement only when a public Application
signature imports it.

The current `docs/README.md` says Fight depends on “PSR-20 (`psr/cache`)”; live Composer metadata has no
`psr/clock` entry and `psr/cache` is PSR-6. That documentation must be corrected independently of whether the
future PSR-20 migration is selected.

## Canonical Namespace Catalog

| Capability | Canonical addition | Compatibility requirement |
| --- | --- | --- |
| PSR-6 read-through cache | `Adapter\Cache\Psr6\Psr6Cache` | Keep `Adapter\Cache\PsrCache` functional and deprecated through 1.x |
| PSR-16 read-through cache | `Adapter\Cache\Psr16\Psr16Cache` | New additive API; no reverse/full-cache support claim |
| PSR-17 JSend response | `Adapter\Http\Psr17\JSendResponseFactory` | New additive API; shared by Slim/Yii and compatible runtimes |
| PSR-15 JSON middleware | `Adapter\Middleware\Psr15\JsonRequestMiddleware` | New additive API; Symfony class remains separate with old-path compatibility |
| PSR-15 error middleware | `Adapter\Middleware\Psr15\JSendErrorMiddleware` | New additive API; same neutral envelope and error policy as native controllers |
| PSR-17 Fight factory bridge | `Adapter\HttpClient\Psr17\Psr17MessageFactory` and focused factories | Add only after body and exception semantics pass provider-independent probes |
| PSR-18 client view | `Adapter\HttpClient\Psr18\Psr18Client` | New additive API; must satisfy PSR-18 exception and HTTP-error rules |
| PSR-20 system clock | `Adapter\Clock\System\SystemClock` | Add only if the standalone composition needs an implementation |
| PSR-11 portable container | `Application\ServiceContainer\Container` | Keep `Application\Service\Container` functional and deprecated through 1.x |

`Psr15`, `Psr16`, `Psr17`, and `Psr18` are provider identifiers in these namespaces, not framework names.
They follow ADR 0023's `Adapter\<Capability>\<Framework-or-Provider>\<Type>` rule. Service-container classes
that register them remain under `Adapter\ServiceContainer\<Framework>`.

## Composer and Verification Consequences

Current production requirements already cover PSR-3, PSR-6, PSR-7, PSR-11, PSR-17, and PSR-18 interfaces
(`composer.json:5-13`). The planned additions imply:

- add `psr/http-server-handler` to `require` when public PSR-15 middleware ships;
- add `psr/simple-cache` to `require-dev` and `suggest` for the optional PSR-16 adapter unless a public inward
  contract later imports it;
- add `psr/clock` to `require` only when a public Application signature consumes `ClockInterface`, or to
  `require-dev` and `suggest` if only an optional Adapter implementation imports it;
- do not add `psr/event-dispatcher` merely to imply Fight messaging compatibility;
- do not add `psr/link` until a hypermedia consumer journey exists.

Each claimed implementation needs the PHP-FIG interface package's conformance behavior plus installed-package
autoload probes. PSR-15/17 tests must use at least two independent PSR-7 implementations or one implementation
plus strict interface doubles so Guzzle conveniences do not become accidental requirements. PSR-18 tests must
lock HTTP-error and exception semantics. Cache probes must include cached `null`. Compatibility probes must
load old and canonical FQCNs independently throughout 1.x.

## Recommended Delivery Order

1. Deliver the neutral JSend envelope already accepted by ADR 0018.
2. Add `Adapter\Http\Psr17\JSendResponseFactory` and its provider-independent response probes.
3. Add PSR-15 JSON and error middleware, then use those exact classes in Slim and compatible Yii journeys.
4. Relocate the PSR-6 cache adapter additively and add the PSR-16 counterpart.
5. Add the PSR-18 outward client view; leave generic PSR-18-to-Fight async adaptation unsupported.
6. Decide PSR-20 adoption capability by capability; never pull the interface into Domain.
7. Keep PSR-13 deferred and PSR-14 explicitly separate from Fight messaging.

This standards-first lane reduces framework-specific code without pretending that similarly named contracts
have identical semantics. Framework adapters remain appropriate for native service-container registration,
native request/response boundaries, queue delivery, persistence, routing, and other behavior that a PSR does
not standardize.
