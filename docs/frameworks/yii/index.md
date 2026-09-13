---
template: atlas-article.html
atlas_article: true
title: Yii
atlas_article_heading_id: yii
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Adapter and Yii application configuration
atlas_component_dependencies: Current supported Yii 3 package set
atlas_article_context: Framework integration · Yii
atlas_article_lead: Register only the selected reusable Yii capability providers and keep application composition explicit, including documented fallbacks.
atlas_article_requires: PHP 8.5+, yiisoft/di
atlas_article_optional: Yii DB, Router, View, Symfony Mailer, Twig, Symfony Filesystem
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Yii application
atlas_relationship_source: Application composition root
atlas_relationship_target_label: Selected Fight seam
atlas_relationship_target: Capability provider
atlas_relationship_description: Application composition supplies collaborators to one bounded Fight capability provider
atlas_relationship_caption: The application selects native or provider-backed composition without changing portable Application contracts.
atlas_consequential_label: Queue unavailable
atlas_consequential_message: Fight Common 1.2 has no stable Yii Queue adapter; experimental starter transport is not a stable support claim.
atlas_next_steps:
  - label: Select providers
    href: "#select-providers"
  - label: Review unavailable Queue
    href: "#queue-is-not-stable-support"
  - label: Choose fallbacks
    href: "#native-and-provider-paths"
  - label: Open the starter
    href: "https://github.com/johnnickell/project-yii"
  - label: Compare frameworks
    href: "../framework-support/"
atlas_local_contents:
  - label: Installation
    href: "#installation-and-ownership"
  - label: Providers
    href: "#select-providers"
  - label: Native and provider paths
    href: "#native-and-provider-paths"
  - label: Queue boundary
    href: "#queue-is-not-stable-support"
  - label: Operations
    href: "#application-owned-operations"
---

## Installation and ownership

Install Fight Common, `yiisoft/di`, and only the Yii/provider packages selected by the
application. The [project-yii starter](https://github.com/johnnickell/project-yii) owns the booted configuration,
package groups, environment values, and lifecycle.

```bash
composer require johnnickell/fight-common yiisoft/di
```

Yii uses its current package set rather than a framework major-line range. Check the
[support matrix](../framework-support/index.md) before changing resolved Yii packages.

## Select providers

Register only the matching bounded service provider from
`Fight\Common\Adapter\ServiceContainer\Yii` for persistence, routing, messaging, HTTP, mail, view, or
filesystem. The application owns the provider list and supplies each provider's collaborators through its own
container definitions.

Persistence uses the selected Yii DB connection. Routing uses Yii's URL generator. Messaging supplies the
application's synchronous Fight bus and dispatcher; it does not imply stable Queue transport. HTTP registers the
configured Fight transport. Mail, view, and filesystem make their fallback or application policy explicit.

## Native and provider paths

Shipped native paths cover Yii DB transactional UnitOfWork and URL generation. Yii cache and logging use their
standard interfaces or the shared PSR lane. Shared adapters provide HTTP, file storage, process, SMS,
publication, observability, and other portable capabilities.

Yii Mail, View, and Filesystem remain native conformance prototypes. The supported paths use Symfony Mailer,
Twig/provider templating, and Symfony Filesystem where the native API has not proven the complete Fight
contract. A fallback is an intentional supported composition, not silent degradation.

## Queue is not stable support

Fight Common 1.2 does not publish a stable Yii Queue adapter because compatible stable `yiisoft/queue` and
production broker releases have not met the gate. A starter may experiment behind the neutral
`CommandMessageHandler` and `EventMessageHandler` seam, but that transport is not a stable framework claim.

Future support requires stable upstream releases plus serialization, acknowledgement, retry, failure, signal,
and long-running-state evidence. Until then, use synchronous Fight messaging or an explicitly application-owned
transport and disclose its status.

## Application-owned operations

The application owns database schema and transactions, cache/log providers, route definitions, templates, mail
transport, filesystem policy, HTTP transport, process limits, scheduled invocation, credentials, health and
metrics publication, and deployment. Provider registration does not choose these policies.

Use the [Repositories](../../components/repositories/index.md),
[Messaging](../../components/messaging/index.md), [Routing](../../components/routing/index.md), and
[Templating](../../components/templating/index.md) guides for component behavior and failure contracts.
