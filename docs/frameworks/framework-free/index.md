---
template: atlas-article.html
atlas_article: true
title: Framework-free
atlas_article_heading_id: framework-free
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Consumer composition root
atlas_component_dependencies: PHP 8.5+, PSR contracts selected by capability
atlas_article_context: Portable integration · Framework-free
atlas_article_lead: Construct portable services directly and add infrastructure adapters only at the application boundary.
atlas_article_requires: PHP 8.5+, johnnickell/fight-common
atlas_article_optional: Doctrine, Guzzle, Flysystem, Twig, Symfony components, Twilio, Mercure
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Composition root
atlas_relationship_source: Explicit factories
atlas_relationship_target_label: Portable behavior
atlas_relationship_target: Application ports
atlas_relationship_description: Explicit factories bind selected infrastructure adapters to portable application ports
atlas_relationship_caption: Domain and Application code remain framework-free; only the outer composition root knows the provider.
atlas_consequential_label: Lifecycle ownership
atlas_consequential_message: Without a framework lifecycle, your bootstrap owns shared versus transient services, shutdown, workers, credentials, and failure reporting.
atlas_next_steps:
  - label: Build the composition root
    href: "#compose-one-capability"
  - label: Register services
    href: "#use-the-fight-container-when-useful"
  - label: Choose providers
    href: "#provider-and-operations-boundaries"
  - label: Compare framework paths
    href: "../framework-support/"
atlas_local_contents:
  - label: Portable baseline
    href: "#the-portable-baseline"
  - label: Compose a capability
    href: "#compose-one-capability"
  - label: Fight container
    href: "#use-the-fight-container-when-useful"
  - label: Provider boundaries
    href: "#provider-and-operations-boundaries"
  - label: Failure boundaries
    href: "#failure-boundaries"
---

## The portable baseline

Framework-free integration is the reference composition, not a reduced support tier. Values, collections,
specifications, messages, repositories, and neutral Application services require no framework adapter. Install
only Fight Common to begin:

```bash
composer require johnnickell/fight-common
```

Use each component guide for its portable construction and behavior. Add an Adapter only when the application
crosses a real infrastructure boundary such as a database, HTTP transport, filesystem, mailer, or process.

## Compose one capability

Application services depend on Domain and Application contracts. The outer bootstrap constructs the chosen
adapter and supplies it to the use case. Keep provider configuration beside that construction so replacing a
transport does not change the use case.

For example, select `GuzzleClient` for outbound HTTP, a Flysystem adapter for file storage,
`SymfonyMailTransport` for mail, or `SymfonyProcessRunner` for processes only when the application uses that
capability. Each optional package is independently selectable; installing Fight Common does not install them.

## Use the Fight container when useful

`Fight\Common\Application\Service\Container` is a small PSR-11 container for explicit composition. Register
shared services with `set()` and transient factories with `factory()`. It does not scan application code or
autowire dependencies.

`Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar` provides bounded helpers for
synchronous messaging, template helpers, and a configured Fight HTTP transport with its PSR-18 view. Invoke only
the registrar for the selected capability. Direct constructor wiring remains valid.

## Provider and operations boundaries

Shared adapters cover Doctrine/DBAL persistence, Guzzle and PSR-18 HTTP, Flysystem storage, Twig templating,
Symfony Mailer/Filesystem/Process/Messenger, Twilio SMS, Mercure publication, and PSR-3 observability. The
application chooses concrete packages, configuration, credentials, migrations, transaction boundaries, retry
policy, worker supervision, and shutdown behavior.

Async delivery through Messenger remains at least once. The application owns brokers, topology, retries,
failure storage, workers, idempotency, and any durable outbox. Portable scheduling likewise needs an
application-owned invocation loop and lock backend.

## Failure boundaries

Do not translate provider failures into success at the composition root. Preserve the component guide's failure
contract, attach operational context at the Adapter edge, and let the Application use case decide whether a
failure is retryable. A null or logging adapter is a deliberate operating mode, not proof that an external side
effect occurred.

Continue with [Dependency Injection](../../components/dependency-injection/index.md),
[Messaging](../../components/messaging/index.md), or the complete
[framework support matrix](../framework-support/index.md).
