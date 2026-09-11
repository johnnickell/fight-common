---
template: atlas-article.html
atlas_article: true
title: Slim
atlas_article_heading_id: slim
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Adapter and explicit PSR-11 composition root
atlas_component_dependencies: Slim 4.15+, selected PSR and provider packages
atlas_article_context: Framework integration · Slim
atlas_article_lead: Compose Slim's HTTP lifecycle with Fight's explicit container registrars and shared provider adapters.
atlas_article_requires: PHP 8.5+, slim/slim 4.15+
atlas_article_optional: PSR-7/15/17 implementations, Twig, Doctrine, Messenger, Mailer, Process
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Slim application
atlas_relationship_source: PSR-11 composition root
atlas_relationship_target_label: Selected Fight seam
atlas_relationship_target: Registrar or provider
atlas_relationship_description: An explicit Slim composition root registers selected Fight capabilities and provider adapters
atlas_relationship_caption: Slim owns the HTTP lifecycle; Fight registrars and shared adapters fill only the selected application boundaries.
atlas_consequential_label: Explicit composition
atlas_consequential_message: Slim has no aggregate Fight provider and no branded copies of shared adapters; middleware order and provider lifecycle remain application-owned.
atlas_next_steps:
  - label: Build the container
    href: "#compose-the-container"
  - label: Configure HTTP
    href: "#http-and-routing"
  - label: Choose providers
    href: "#shared-provider-paths"
  - label: Open the starter
    href: "https://github.com/johnnickell/project-slim"
  - label: Compare frameworks
    href: "../framework-support/"
atlas_local_contents:
  - label: Installation
    href: "#installation-and-ownership"
  - label: Container
    href: "#compose-the-container"
  - label: HTTP and routing
    href: "#http-and-routing"
  - label: Providers
    href: "#shared-provider-paths"
  - label: Operations
    href: "#application-owned-operations"
---

## Installation and ownership

Install Fight Common, Slim, a PSR-7 implementation, and only the provider packages selected by the application.
The [project-slim starter](https://github.com/johnnickell/project-slim) owns the executable bootstrap, container
definitions, middleware order, routes, and runtime policy.

```bash
composer require johnnickell/fight-common slim/slim
```

Slim is intentionally close to the framework-free path: portable Fight services remain unchanged and the
application composes infrastructure explicitly.

## Compose the container

Use the application's PSR-11 container or `Fight\Common\Application\Service\Container`. Bounded methods on
`Fight\Common\Adapter\ServiceContainer\Fight\ContainerCapabilityRegistrar` register selected synchronous
messaging, template-helper, and HTTP-client integrations. Direct constructor wiring is also supported.

There is no aggregate Slim provider. Supply explicit handler maps, subscriber maps, filters, helper services,
and collaborators rather than scanning application code or activating optional packages implicitly.

## HTTP and routing

Slim uses the shared PSR-15 middleware and PSR-17 response lane. `SlimUrlGenerator` translates Fight's portable
URL-generation contract to Slim named routes. The application owns route registration, base path, middleware
order, error middleware, response factories, and request lifecycle.

Do not add Slim-branded wrappers for capabilities already expressed honestly through a PSR or provider adapter.

## Shared provider paths

Select provider adapters independently: Doctrine/DBAL for persistence, Symfony Messenger for async messaging,
Twig for templates, Symfony Mailer for mail, Symfony Filesystem and Process, Guzzle/PSR-18 for outbound HTTP,
Flysystem for storage, Twilio for SMS, Mercure for publication, and PSR-3 plus shared health/audit/metrics for
observability.

Async delivery is at least once. The application owns Messenger transports, retries, failures, workers,
idempotency, and durable outbox policy. Portable scheduling likewise needs application-owned invocation and
locking.

## Application-owned operations

Keep database schema, provider configuration, credentials, route and middleware policy, templates, worker
supervision, process limits, scheduled invocation, logs, health checks, and deployment in the Slim project.
Installing a provider proves availability, not activation or production readiness.

Use the [HTTP](../../components/http-client/index.md), [Routing](../../components/routing/index.md),
[Messaging](../../components/messaging/index.md), and
[Dependency Injection](../../components/dependency-injection/index.md) guides for component behavior. Use the
[support matrix](../framework-support/index.md) for capability state.
