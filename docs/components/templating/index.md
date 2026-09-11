---
template: atlas-article.html
atlas_article: true
title: Templating
atlas_article_heading_id: templating
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Twig, Laravel Blade, Yii View, or CodeIgniter Twig fallback
atlas_article_context: Application · Adapter
atlas_article_lead: Render named templates through one application port, then choose the engine and escaping policy at the boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: twig/twig, laravel/framework, yiisoft/view, codeigniter4/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: PHP, Twig, Blade, or Yii View
atlas_relationship_target_label: Application port
atlas_relationship_target: TemplateEngine
atlas_relationship_description: Rendering engines fulfill the portable template contract
atlas_relationship_caption: Application code chooses a template and data; adapters own lookup, engine behavior, helper exposure, and rendering failures.
atlas_consequential_label: Output boundary
atlas_consequential_message: Escaping depends on the selected engine and output context; never treat rendered HTML, JavaScript, URLs, or email content as safe without the correct contextual escaping policy.
atlas_next_steps:
  - label: Render a template
    href: "#templateengine-interface"
  - label: Choose an engine
    href: "#phpengine"
  - label: Delegate by suffix
    href: "#delegatingengine"
  - label: Send rendered email
    href: "../mail/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Template engine
    href: "#templateengine-interface"
  - label: Helpers
    href: "#templatehelper-interface"
  - label: PHP engine
    href: "#phpengine"
  - label: Twig engine
    href: "#twigengine"
  - label: Delegating engine
    href: "#delegatingengine"
  - label: Failures
    href: "#exceptions"
---

Templating keeps rendering behind `TemplateEngine`. The selected adapter determines template discovery,
syntax, inheritance, helper exposure, caching, and escaping. Do not promise interchangeable template files:
portability is at the render contract, not at engine syntax.

--8<-- "docs/templating.md"

For delivery after rendering an email body, see the [Mail guide](../mail/index.md).
