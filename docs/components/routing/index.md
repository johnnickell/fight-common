---
template: atlas-article.html
atlas_article: true
title: Routing
atlas_article_heading_id: routing
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Symfony, Laravel, Yii, Slim, or CodeIgniter router
atlas_article_context: Application · Adapter
atlas_article_lead: Generate links by route name through a portable port while the framework adapter owns route lookup and parameter rules.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/routing, laravel/framework, yiisoft/router, slim/slim, codeigniter4/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Framework router
atlas_relationship_target_label: Application port
atlas_relationship_target: UrlGenerator
atlas_relationship_description: Framework URL generators fulfill the application routing contract
atlas_relationship_caption: Application code names routes and supplies values; the adapter translates framework-specific lookup, absolute URL, and failure behavior.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Path parameters and query values have different roles; missing or invalid route parameters fail generation and must not be silently moved into the query string.
atlas_next_steps:
  - label: Generate a URL
    href: "#urlgenerator-interface"
  - label: Map failures
    href: "#exception-mapping"
  - label: Choose a framework adapter
    href: "../../frameworks/framework-support/"
  - label: Render the link
    href: "../templating/"
atlas_local_contents:
  - label: URL generator
    href: "#urlgenerator-interface"
  - label: Symfony adapter
    href: "#symfonyurlgenerator"
  - label: Exception mapping
    href: "#exception-mapping"
  - label: Failures
    href: "#exceptions"
---

Routing exposes one portable `UrlGenerator` contract. Symfony, Laravel, Yii, Slim, and CodeIgniter
adapters preserve the same application call shape while translating their native route collections and
exceptions. Route definitions and framework boot remain consumer-owned.

--8<-- "docs/routing.md"
