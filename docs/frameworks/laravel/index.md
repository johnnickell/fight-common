---
template: atlas-article.html
atlas_article: true
title: Laravel
atlas_article_heading_id: laravel
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Adapter and Laravel application providers
atlas_component_dependencies: laravel/framework 13
atlas_article_context: Framework integration · Laravel
atlas_article_lead: Register only the Fight service providers for capabilities your Laravel application selects.
atlas_article_requires: PHP 8.5+, Laravel 13
atlas_article_optional: Selected provider packages for shared adapters
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Laravel application
atlas_relationship_source: Provider registration
atlas_relationship_target_label: Selected Fight seam
atlas_relationship_target: Capability provider
atlas_relationship_description: Laravel provider registration binds one selected Fight capability
atlas_relationship_caption: Bounded providers expose native adapters without silently activating unrelated capabilities.
atlas_consequential_label: Application-owned delivery
atlas_consequential_message: Native Queue and broadcasting adapters do not own queues, retries, failed jobs, channels, authorization, workers, or outbox policy.
atlas_next_steps:
  - label: Select providers
    href: "#select-capability-providers"
  - label: Configure Queue
    href: "#queue-and-broadcasting"
  - label: Review provider choices
    href: "#native-and-fallback-paths"
  - label: Open the starter
    href: "https://github.com/johnnickell/project-laravel"
  - label: Compare frameworks
    href: "../framework-support/"
atlas_local_contents:
  - label: Installation
    href: "#installation-and-ownership"
  - label: Providers
    href: "#select-capability-providers"
  - label: Native and fallback paths
    href: "#native-and-fallback-paths"
  - label: Queue and broadcasting
    href: "#queue-and-broadcasting"
  - label: Operations
    href: "#application-owned-operations"
---

## Installation and ownership

Install Fight Common with Laravel. The [project-laravel starter](https://github.com/johnnickell/project-laravel)
owns the application bootstrap, provider registration, configuration publication, migrations, workers, and
deployment.

```bash
composer require johnnickell/fight-common laravel/framework
```

Fight Common does not auto-enable every integration. Application code should continue to depend on portable
Fight contracts rather than Laravel adapters.

## Select capability providers

Register only the needed providers under `Fight\Common\Adapter\ServiceContainer\Laravel`. Available bounded
providers cover messaging, persistence, security, cache, HTTP responses, routing, templating, mail,
broadcasting, file storage, filesystem, HTTP client, process, metrics, and logging.

Provider selection is the activation boundary. Do not create an application provider that registers every Fight
provider by default, and do not rely on unrelated package discovery to choose infrastructure policy.

## Native and fallback paths

Shipped native paths include Queue messaging, transactional UnitOfWork, password hashing and validation, cache,
JSend/error responses, URL generation, Blade, mail, broadcasting, and filesystem. Laravel's PSR-3 logger is wired
directly.

Native file storage, HTTP client, process, and Pulse metrics remain prototype decisions with the documented
Flysystem, Guzzle/PSR-18, Symfony Process, and shared metrics paths available. Select the proven path required by
the application; a prototype label is not a stable adapter promise.

## Queue and broadcasting

`MessagingServiceProvider` binds the asynchronous Fight ports to `LaravelCommandBus` and
`LaravelEventDispatcher`. Jobs transport complete Fight messages and delegate them to neutral synchronous
handlers. Delivery is at least once, so handlers must tolerate duplicate attempts and repeated event fan-out.

`BroadcastingServiceProvider` selects both `LaravelBroadcastPublisher` and `LaravelPrivatePublisher`. The
application owns channel definitions, authorization, broadcaster configuration, queues, retries, failed jobs,
worker supervision, and durable outbox policy.

## Application-owned operations

Keep database schema, transaction use, cache stores, filesystem disks and path policy, route definitions, HTTP
middleware, templates, mail configuration, process limits, schedules, credentials, monitoring, and deployment in
the application. Registering a provider proves composition, not production operations readiness.

Use the [Messaging](../../components/messaging/index.md), [Cache](../../components/cache/index.md),
[Routing](../../components/routing/index.md), and [Mail](../../components/mail/index.md) guides for behavior and
failures. Use the [support matrix](../framework-support/index.md) for the complete capability state.
