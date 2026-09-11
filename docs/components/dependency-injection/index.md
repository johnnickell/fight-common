---
template: atlas-article.html
atlas_article: true
title: Dependency Injection
atlas_article_heading_id: dependency-injection
atlas_component_group: Coordinate Application Behavior
atlas_component_owner: Application
atlas_component_dependencies: PHP 8.5+, PSR-11 ContainerInterface
atlas_article_context: Application
atlas_article_lead: Assemble a small application composition root with a PSR-11 container when a consumer does not provide its own framework container.
atlas_article_requires: PHP 8.5+, psr/container
atlas_article_optional: None
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Application composition root
atlas_relationship_source: Container
atlas_relationship_target_label: Application service
atlas_relationship_target: service factory
atlas_relationship_description: A composition root registers shared services or prototype factories behind PSR-11 lookup
atlas_relationship_caption: The container is an Application utility; framework configuration and Adapter wiring remain consumer-owned boundaries.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: set() caches one factory result per registration, factory() creates a result per get(), and an unknown service ID throws the PSR-11 NotFoundException.
atlas_next_steps:
  - label: Coordinate commands and queries
    href: "../messaging/"
  - label: Validate application input
    href: "../validation/"
  - label: Choose a repository boundary
    href: "../repositories/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: When to use this container
    href: "#when-to-use-this-container"
  - label: PSR-11 compliance
    href: "#psr-11-compliance"
  - label: Shared services
    href: "#services-shared"
  - label: Prototype factories
    href: "#factories-prototype"
  - label: Parameters
    href: "#parameters"
  - label: Not found exception
    href: "#notfoundexception"
---

The container is a small Application-level PSR-11 implementation for an explicit composition root.
Use it where a consumer needs direct portable wiring; when a framework supplies its own container,
keep framework configuration and Adapter registration in that consumer rather than treating this
class as a framework integration.

**Ownership.** `Application\Service\Container` and its PSR-11 exception are Application code. A
consumer owns service IDs, factories, framework bindings, secrets, lifecycle policy, and the choice
to use a framework container instead. The package does not provide autowiring, reflection-based
resolution, or an Adapter configuration format.

**Dependencies.** The container requires PHP 8.5+ and the required `psr/container` package. No
framework dependency is required.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with an explicit application composition root.**

```php-inline
use Fight\Common\Application\Service\Container;

$container = new Container();
$container->set('clock', fn (): SystemClock => new SystemClock());
$container->factory('request-id', fn (): string => bin2hex(random_bytes(16)));

$clock = $container->get('clock');       // shared within this registration
$requestId = $container->get('request-id'); // regenerated on every get()
```

`get()` only resolves registered services: a missing ID throws
`Fight\Common\Application\Service\Exception\NotFoundException`, which implements PSR-11's
`NotFoundExceptionInterface`. `ArrayAccess` and parameter methods are a separate key-value store,
not aliases for services. Keep credentials out of source and provide them through the consuming
application's secure configuration boundary.

## Reference

--8<-- "docs/dependency-injection.md"
