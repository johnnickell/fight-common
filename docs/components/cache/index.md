---
template: atlas-article.html
atlas_article: true
title: Cache
atlas_article_heading_id: cache
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: PSR-6 or PSR-16, optional framework cache
atlas_article_context: Application · Adapter
atlas_article_lead: Load reusable values through an application cache port, then select the cache store at the composition boundary.
atlas_article_requires: PHP 8.5+, psr/cache
atlas_article_optional: psr/simple-cache, laravel/framework, codeigniter4/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: PSR-6, PSR-16, Laravel, or CodeIgniter cache
atlas_relationship_target_label: Application port
atlas_relationship_target: Cache and MutableCache
atlas_relationship_description: Cache adapters fulfill read-through and mutable cache contracts
atlas_relationship_caption: Application code owns keys, TTL intent, and fallback behavior; the adapter owns store access.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Cache values are disposable and may be stale or absent; never make the cache the only durable record of business state.
atlas_next_steps:
  - label: Load through the cache
    href: "#caching-a-database-query"
  - label: Choose a cache contract
    href: "#cache-interface"
  - label: Handle failures
    href: "#cacheexception"
  - label: Store durable files
    href: "../files/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Cache port
    href: "#cache-interface"
  - label: PSR-6 adapter
    href: "#psr6cache"
  - label: Compatibility
    href: "#deprecated-psrcache-compatibility"
  - label: Failures
    href: "#cacheexception"
  - label: Configuration
    href: "#symfony-configuration"
  - label: Usage examples
    href: "#usage-examples"
---

Cache is an optimization boundary, not storage. The portable `Cache` contract owns read-through loading;
`MutableCache` adds explicit deletion and clearing where a use case genuinely needs invalidation. Select PSR-6,
PSR-16, Laravel, or CodeIgniter at the composition root.

--8<-- "docs/cache.md"
