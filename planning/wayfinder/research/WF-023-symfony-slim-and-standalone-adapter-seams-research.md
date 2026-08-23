# WF-023 Symfony, Slim, and standalone adapter seams research

**Date:** 2026-08-22
**Ticket:** [Research Symfony, Slim, and standalone adapter seams](../tickets/WF-023-symfony-slim-and-standalone-adapter-seams.md)

## Executive finding

Fight Common already contains the right three shapes, but their names obscure them:

1. Symfony-native extension points and runtime adapters that should remain Symfony-specific;
2. provider adapters such as Doctrine, Flysystem, Guzzle, Twig, Twilio, and Mercure that are reusable from
   several frameworks; and
3. two message-consumer handlers that are framework-neutral despite their Symfony names.

Slim does not justify a parallel branded adapter for every capability. Its HTTP and container integration is
intentionally PSR-based, so Fight Common should publish PSR-15/PSR-17 response and middleware adapters where
the same implementation can serve Slim and another compatible framework. Slim can then compose the existing
Doctrine, Symfony Messenger, Twig, Symfony Mailer, Symfony Process, Flysystem, Guzzle, Monolog/PSR-3, Twilio,
and Mercure adapters without pretending those providers are native Slim facilities.

Bernard is not a viable supported queue line for Fight Common 1.2: its newest published core is an alpha from
2018 with PHP 5.6/7 constraints, and its Symfony bundle is tied to Symfony 2/3-era requirements. It may remain
historical research, but it should not enter the supported matrix.

## Live-source classification

| Current surface | Canonical direction | Reason |
| --- | --- | --- |
| Seven `Adapter\DependencyInjection` compiler passes | `Adapter\ServiceContainer\Symfony\*` | All implement Symfony's container compilation/tag-discovery extension mechanism. Symfony documents compiler passes as pre-runtime container mutation and tagged-service processing. |
| `MessengerCommandBus` and `MessengerEventDispatcher` | `Adapter\Messaging\Symfony\*` | Both depend directly on Messenger `Envelope` and `SenderInterface`; they are transport submission adapters, not neutral buses. |
| `SymfonyMessageSerializer` | `Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer` | It implements Messenger's transport `SerializerInterface` and persists Messenger stamps. |
| `SymfonyCommandMessageHandler` and `SymfonyEventMessageHandler` | neutral `Adapter\Messaging\Handler\CommandMessageHandler` and `EventMessageHandler` plus legacy shims | They import no Symfony type and only dispatch Fight `CommandMessage`/`EventMessage` to the synchronous Fight contracts. Laravel Jobs and Messenger handlers can delegate to the same consumers. |
| `JsonRequestMiddleware` | `Adapter\Middleware\Symfony\JsonRequestMiddleware` | It decorates Symfony `HttpKernelInterface`; it is middleware rather than a generic HTTP response. |
| `ErrorController` | `Adapter\Http\Symfony\Controller\ErrorController` | It translates exceptions into the Symfony-native JSend response. |
| exception and validation subscribers | `Adapter\Middleware\Symfony\*` or a narrowly named Symfony event-subscriber leaf selected during synthesis | They participate in Symfony's request pipeline. Keep them beside middleware rather than in top-level `EventSubscriber`. |
| `JSendResponse` | typed envelope plus `Adapter\Http\Symfony\JSendResponse`; add a PSR response factory adapter | The legacy class is a Symfony `JsonResponse`. PSR-15 recommends a response factory for portable middleware and Slim requires PSR-7 responses. |
| Doctrine repositories, types, and UnitOfWork | `Adapter\Persistence\Doctrine\*` | Provider-owned persistence is reusable by Symfony and Slim and remains independent of either framework kernel. |
| Symfony Filesystem, Mailer, Process, Routing | existing capability-first Symfony/provider leaves | These already represent actual Symfony component APIs and can be used in Slim without renaming them as Slim adapters. |
| PSR cache, Guzzle, Flysystem, Twig, Twilio, Mercure, logging/null decorators | retain provider or neutral capability paths | These are already cross-framework adapters. Framework service-container classes may wire them without duplicating runtime behavior. |

The current inventory supporting this classification is in `src/Adapter`, and the inward ports are in
`src/Application`. In particular, the current Messenger handlers have no framework import, while the buses
and serializer directly import Symfony Messenger.

## Symfony service-container and Messenger facts

Symfony compiler passes run while the container is compiled and can process tagged services, modify
definitions, aliases, and service locators. A reusable Fight Common compiler pass therefore belongs to the
service-container capability even when the services it discovers are command handlers or template helpers.
Projects still decide which passes to register and configure. [Symfony compiler-pass documentation](https://symfony.com/doc/current/service_container/compiler_passes.html)

Messenger supports multiple buses, routing messages to transports, custom transport serializers, retry
strategies, failure transports, worker signals, and transaction middleware. Those operational choices remain
project configuration. Fight Common's reusable surface is the transport-facing bus/dispatcher, its serializer,
the neutral consumer handler, and optional Symfony service-container wiring. [Symfony Messenger configuration reference](https://symfony.com/doc/current/reference/configuration/framework.html#messenger)

`DispatchAfterCurrentBusStamp` delays nested dispatch until the current bus finishes; it is not equivalent to
an application database commit in every composition. The adapter matrix must distinguish Messenger bus-order
semantics from the explicit Fight `UnitOfWork::commitTransactional()` boundary and any durable outbox policy.
[Symfony Messenger component documentation](https://symfony.com/doc/8.0/components/messenger.html)

Symfony Mailer can itself route mail through Messenger for asynchronous delivery. That is a Symfony runtime
composition of the existing Mail adapter and Messenger, not a new portable mail contract.
[Symfony Mailer async documentation](https://symfony.com/doc/current/mailer.html#sending-messages-async)

## Slim and PSR seams

Slim 4 accepts an optional PSR-11 container and can create its application through
`AppFactory::createFromContainer()`. Fight Common should therefore provide definitions or a small opt-in
service-container integration for the selected container, not make Slim depend on Symfony's container.
[Slim dependency-container documentation](https://www.slimframework.com/docs/v4/concepts/di.html)

Slim middleware consumes PSR-7 server requests and returns PSR-7 responses. A reusable Fight middleware or
JSend response adapter should target PSR-15 and a PSR-17 response factory before adding a Slim-branded class.
[Slim middleware documentation](https://www.slimframework.com/docs/v4/concepts/middleware.html) and
[PSR-15](https://www.php-fig.org/psr/psr-15/)

Slim routing and controller invocation remain application composition. A Fight URL-generator adapter may be
warranted only if Slim's named-route API cannot be bound directly behind `Application\Routing\UrlGenerator`;
the service-container research should not invent a controller-registration abstraction.
[Slim routing documentation](https://www.slimframework.com/docs/v4/objects/routing.html)

Slim's own Doctrine cookbook confirms that Doctrine is composed through a project-selected PSR-11 container.
This supports one Doctrine persistence adapter shared with Symfony rather than a duplicated Slim persistence
implementation. [Slim Doctrine cookbook](https://www.slimframework.com/docs/v4/cookbook/database-doctrine.html)

## Standalone provider decisions

- **Monolog:** use it directly as a PSR-3 implementation. Monolog already implements `LoggerInterface` and
  provides handler/formatter/processor composition; a Fight-branded Monolog logger would add no translation.
  Fight Common may wire a supplied PSR-3 logger into its logging decorators.
  [Monolog README](https://github.com/Seldaek/monolog) and
  [handler/processor documentation](https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md)
- **Bernard:** do not support it in the 1.2 matrix. Packagist shows `1.0.0-alpha9` from 2018 with PHP 5.6/7
  constraints; the bundle's latest stable release requires Symfony 2.7/3.0-era components.
  [Bernard core package](https://packagist.org/packages/bernard/bernard) and
  [Bernard bundle package](https://packagist.org/packages/bernard/bernard-bundle)
- **Existing providers:** Doctrine, Flysystem, Guzzle, Twig, Twilio, Mercure, Symfony Mailer, and Symfony
  Process already cross framework boundaries through their own APIs. Prefer service-container wiring and
  conformance tests over framework-branded wrapper duplication.

## Decisions unblocked for the framework-adapter support matrix

1. Relocate every existing Symfony compiler pass to `Adapter\ServiceContainer\Symfony` with old-path
   compatibility; revise the current T-00051 and T-00052 destinations.
2. Rename the two neutral message handlers additively and let Symfony Messenger and Laravel Jobs delegate to
   them.
3. Add a PSR HTTP response/middleware lane for Slim and compatible frameworks before creating redundant Slim
   classes.
4. Keep Doctrine persistence provider-owned and move all Repository/UnitOfWork declarations under
   `Adapter\Persistence`.
5. Treat workers, retry counts, brokers, failure stores, routing configuration, and process supervision as
   starter/runtime operations unless a framework API requires a reusable translation adapter.
6. Reject Bernard as a supported dependency and use Monolog through PSR-3 rather than a new logger adapter.
