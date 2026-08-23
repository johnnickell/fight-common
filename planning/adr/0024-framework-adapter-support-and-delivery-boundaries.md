# ADR 0024: Framework Adapter Support and Delivery Boundaries

- Status: accepted
- Date: 2026-08-23

## Context

Fight Common supports Symfony, Laravel, Yii, CodeIgniter, and Slim through a mixture of native framework
adapters, provider-neutral adapters, accepted PSR contracts, framework service-container registration, and
starter-owned composition. Equivalent capability names do not guarantee equivalent lifecycle or delivery
behavior, and a supported installation needs more than an autoloadable framework-named class.

This record is the accepted result of WF-024's documented grilling session and governs the subsequent
specification and ticket handoff.

## Decisions Settled

### Queued Fight message delivery

Each stable framework async adapter transports one complete Fight `CommandMessage` or `EventMessage`, preserving
its message identity, creation time, payload, and metadata. A framework job or listener delegates the received
message to the neutral Fight message handler, which invokes the synchronous Fight bus or dispatcher.

Delivery is at least once. Framework-native post-commit submission is used where available, but it is not an
atomic outbox. Broker selection, queue topology, retry and failure policy, worker supervision, and a durable
outbox remain starter or application responsibilities. Event handlers must tolerate a retry of the complete
Fight event fan-out.

### Yii queue release gate

Fight Common 1.2 does not publish a stable Yii Queue adapter while `yiisoft/queue` and its production broker
adapters have no compatible stable release. Fight Common may publish the neutral handlers and Yii registration
needed by a starter-owned experimental prototype. Stable support requires stable upstream tags and queue
serialization, retry, acknowledgement, failure, signal, and long-running-state evidence.

### Authentication ownership

Fight Common 1.2 adapts only authentication and security seams that preserve the current public contract, such
as password hashing and the existing neutral HMAC and JWT capabilities. It does not disguise a framework guard,
identity, challenge, or session facility behind the current PSR-7 boolean `Authenticator` contract.

The downstream Fight AccessControl project owns project-level authentication adaptation and principal mapping.
If consumers need a shared invocation-neutral authenticated-principal result, that is a separate contract design
rather than a lossy 1.2 adapter.

### Capability-scoped activation

Framework service-container integration remains capability-scoped and explicitly selected. Fight Common does
not publish one aggregate provider that silently activates every optional adapter. Laravel providers, Symfony
compiler passes, Yii providers/config groups, CodeIgniter service delegates, and portable container registration
classes expose only their bounded capability.

Fight Common also provides capability-scoped registration classes for its own concrete PSR-11 container. These
registrars accept explicit command-handler, query-handler, event-subscriber, filter, helper, and collaborator
service definitions. The container's existing service factories are callbacks, so the registration API does not
add a second general callback mechanism or scan project code implicitly.

### Native-first adaptation and companion packages

Fight Common attempts a framework-native adapter when the framework has a current public API for the capability.
The adapter ships only after tests prove the complete Fight contract. If the native API cannot express a required
operation or value, the implementation ticket reports that exact gap for a new decision instead of silently
omitting it.

Official companion packages are evaluated case by case rather than automatically preferred over a small native
adapter. CodeIgniter's core `CacheInterface`, for example, is current and exposes `remember()` directly. A small
`Adapter\Cache\CodeIgniter\CodeIgniterCache` is therefore a valid prototype even though the separately installed
official `codeigniter4/cache` package can expose the same native cache through PSR-6 and PSR-16.

### Optional framework dependencies

Fight Common does not install every supported framework or optional provider in production. Framework and
provider packages used to build and test optional adapters belong in Fight Common's development requirements and
Composer suggestions according to the existing package policy. Each starter requires only its selected framework
and provider stack.

### Support evidence

The initial support rule requires both library-owned adapter conformance evidence and a booted installed-package
journey in the corresponding starter repository. This rule is intentionally reviewable during implementation if
its cost proves disproportionate; changing it requires an explicit planning decision rather than silently
weakening a support claim.

## Accepted Framework Catalog

The catalog uses three delivery states: **ship** for a clear adapter contract, **prototype** when the native API
must first prove the complete Fight contract, and **wire** when an existing standard or provider adapter already
supplies the capability.

### Shared

Fight Common ships PSR-15 JSON and JSend error middleware, a PSR-17 JSend response factory, canonical PSR-6 and
PSR-16 cache adapters, neutral command/event message handlers, capability registrars for Fight's container, and
the existing Doctrine, Flysystem, Guzzle, Twig, Symfony component, Twilio, and Mercure provider adapters.

`Adapter\HttpClient\Psr18\Psr18Client` implements PSR-18 and composes the existing
`Application\HttpClient\Transport\HttpClient`. It exposes that client's synchronous `send()` behavior through
`sendRequest()` for consumers that require PSR-18; the original Fight client retains its asynchronous operation.
The adapter does not claim that an arbitrary synchronous PSR-18 client implements Fight's larger HTTP-client
contract. Its conformance tests cover ordinary HTTP error responses and PSR-18 request/network exceptions.
Service-container integrations may register the same configured transport as Fight `HttpClient` and register a
`Psr18Client` decorating it as PSR-18 `ClientInterface`, allowing each consumer to request its required contract.

### Laravel

Fight Common ships native async command/event delivery, transactional UnitOfWork, password hashing/validation,
cache, JSend/error response, URL generation, Blade, mail, broadcasting, and capability service providers. Native
FileStorage, Filesystem, HTTP-client, Process, and Pulse-metrics adapters begin as conformance prototypes. The
Laravel PSR-3 logger and existing provider adapters are wired directly when a prototype adds no useful behavior.

### Yii

Fight Common ships Yii DB transactional UnitOfWork, URL generation, capability providers, and the shared PSR HTTP
lane. Yii Mail, View, and Filesystem adapters begin as conformance prototypes. Yii's standard interfaces and the
existing provider adapters supply cache, logging, HTTP client, file storage, process, SMS, socket, health, audit,
and metrics. Stable async queue support remains release-gated.

### CodeIgniter

Fight Common ships a native cache adapter, transactional UnitOfWork, Queue command/event delivery, JSend/error
response, URL generation, and capability service delegates. Native Mail, templating, and Filesystem adapters
begin as conformance prototypes. CodeIgniter's PSR-3 logger and existing provider adapters supply outbound HTTP,
file storage, process, SMS, socket, health, audit, and metrics.

### Symfony

Fight Common retains and canonically namespaces its Messenger buses and serializer, Symfony HTTP middleware,
JSend/error response, capability compiler passes, Doctrine persistence, and Symfony Mailer, Filesystem, Process,
and Routing adapters. Neutral handlers consume queued messages. Fight Common does not add an aggregate Symfony
Bundle.

### Slim

Slim uses the shared PSR HTTP lane, Fight's container and capability registrars, and the existing provider
adapters. Fight Common adds a small Slim URL generator for the named-route translation, but does not add branded
copies of shared adapters.

## Release Boundaries

Every catalog item marked **ship** is required before Fight Common 1.2 claims the corresponding five-framework
support. The work remains additive for 1.2; an adapter requiring an incompatible inward contract change is moved
individually to 2.0 rather than weakening the existing contract.

A failed native conformance prototype does not block 1.2 when the matrix retains a documented, tested provider
or standard implementation for that capability. It does block the support claim when no accepted composition
remains. Later compatible upstream releases or newly proven additive adapters may ship in 1.3 before the 2.0
namespace cleanup. A stable Yii Queue integration is an explicit example of a possible 1.3 addition.

## Planning Handoff

WF-024 handed its accepted decisions to `/to-spec`, which refreshed PRD-00014 for Fight Common public adapters
and compatibility and PRD-00015 for framework support, activation, and support claims. `/to-tickets` then
preserved valid existing tickets, rewrote tickets whose namespace or adapter assumptions changed, and added the
missing vertical slices.

The intended ticket slices are shared PSR/container integration; Symfony reconciliation; Laravel messaging,
transactions, and container integration; remaining Laravel adapters and prototypes; Yii stable adapters and
prototypes; CodeIgniter messaging, transactions, and container integration; remaining CodeIgniter adapters and
prototypes; and final dependency, documentation, starter-receipt, and certification integration. Existing
T-00049, T-00053, and T-00059 remain valid. T-00050 through T-00052, T-00054, and T-00058 are rewritten, and
T-00069 through T-00075 publish the shared, framework-native, and final evidence slices.
