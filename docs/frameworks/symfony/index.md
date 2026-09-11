---
template: atlas-article.html
atlas_article: true
title: Symfony
atlas_article_heading_id: symfony
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Adapter and Symfony application container
atlas_component_dependencies: Selected Symfony 8.1+ components
atlas_article_context: Framework integration · Symfony
atlas_article_lead: Register selected compiler passes and provider adapters in the application container without introducing a Fight bundle.
atlas_article_requires: PHP 8.5+, selected Symfony 8.1+ components
atlas_article_optional: Messenger, Doctrine, Mailer, Filesystem, Process, Routing, Twig, Mercure
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Symfony application
atlas_relationship_source: Container configuration
atlas_relationship_target_label: Selected Fight seam
atlas_relationship_target: Compiler pass or adapter
atlas_relationship_description: Symfony configuration activates one selected Fight capability at a time
atlas_relationship_caption: The application owns service loading and aliases; Fight supplies bounded compiler passes and provider adapters.
atlas_consequential_label: No bundle
atlas_consequential_message: Fight Common has no Symfony bundle; the project must explicitly register each selected compiler pass, component, alias, and environment value.
atlas_next_steps:
  - label: Select activation
    href: "#activate-selected-capabilities"
  - label: Configure Messenger
    href: "#messaging-and-workers"
  - label: Review operations
    href: "#application-owned-operations"
  - label: Open the starter
    href: "https://github.com/johnnickell/project-symfony"
  - label: Compare frameworks
    href: "../framework-support/"
atlas_local_contents:
  - label: Installation
    href: "#installation-and-ownership"
  - label: Activation
    href: "#activate-selected-capabilities"
  - label: Provider composition
    href: "#native-and-provider-composition"
  - label: Messaging
    href: "#messaging-and-workers"
  - label: Operations
    href: "#application-owned-operations"
---

## Installation and ownership

Install Fight Common plus only the Symfony components required by the application. Symfony packages are optional
Fight Common dependencies; the [project-symfony starter](https://github.com/johnnickell/project-symfony) owns
the booted application, service configuration, environment values, and lifecycle.

```bash
composer require johnnickell/fight-common symfony/dependency-injection
```

Portable Domain and Application code stays unchanged. Symfony configuration belongs at the Adapter edge.

## Activate selected capabilities

Register only the compiler passes needed by the application from
`Fight\Common\Adapter\ServiceContainer\Symfony`: command handlers, command filters, query handlers, query
filters, event subscribers, template helpers, and event-mapping providers. Each pass owns one bounded discovery
or registration concern.

Fight Common deliberately provides no aggregate Symfony bundle. The application owns service resource loading,
autoconfiguration, compiler-pass registration, public aliases, transport DSNs, and environment configuration.

## Native and provider composition

Use canonical Symfony adapters where Symfony owns the integration seam: Messenger buses and serialization,
HTTP middleware and JSend responses, routing, Mailer, Filesystem, Process, and Doctrine persistence. Compose Twig,
Mercure, cache, PSR HTTP, and other shared provider lanes directly when no additional framework translation is
needed.

Do not add a Fight wrapper around PSR-3, PSR-7, or another accepted contract solely for symmetry. See the
individual component guide for construction, errors, and consequential behavior.

## Messaging and workers

`MessengerCommandBus`, `MessengerEventDispatcher`, and `SymfonyMessageSerializer` transport complete Fight
messages. Neutral `CommandMessageHandler` and `EventMessageHandler` invoke the configured synchronous bus or
dispatcher after receipt.

Delivery is at least once. Configure transports, routing, retries, failure transports, workers, signals, and
supervision in the Symfony application. Post-commit submission is not an atomic outbox, and an event retry
repeats the complete synchronous fan-out.

## Application-owned operations

The application owns migrations, transaction boundaries, secrets, route definitions, middleware order, mail
transport, process limits, scheduled invocation, logs, metrics, health checks, and deployment. A component being
installed does not activate its Fight adapter.

Use the [Messaging](../../components/messaging/index.md), [Routing](../../components/routing/index.md),
[Mail](../../components/mail/index.md), and [Process](../../components/process/index.md) guides for behavior.
Use the [support matrix](../framework-support/index.md) to distinguish shipped adapters from direct wiring.
