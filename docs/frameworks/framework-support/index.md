---
template: atlas-article.html
atlas_article: true
title: Framework Support
atlas_article_heading_id: framework-support
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Support contract
atlas_component_owner: Adapter and consumer composition root
atlas_component_dependencies: Selected framework and capability providers
atlas_article_context: Framework integration
atlas_article_lead: Choose a supported composition path without activating adapters your application does not use.
atlas_article_requires: PHP 8.5+, johnnickell/fight-common
atlas_article_optional: Symfony 8.1+, Laravel 13, Yii 3, CodeIgniter 4.7+, or Slim 4.15+
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Consumer composition root
atlas_relationship_source: Selected framework
atlas_relationship_target_label: Fight boundary
atlas_relationship_target: Capability adapter
atlas_relationship_description: A consumer activates one selected framework capability at a time
atlas_relationship_caption: Portable Domain and Application behavior stays unchanged while the composition root selects only the adapters and providers it needs.
atlas_consequential_label: Support boundary
atlas_consequential_message: A framework constraint or autoloadable class is not a support claim; shipped adapters require conformance and a booted starter receipt.
atlas_next_steps:
  - label: Choose a path
    href: "#choose-an-integration-path"
  - label: Read the support matrix
    href: "#reading-the-matrix"
  - label: Start framework-free
    href: "../framework-free/"
  - label: Install the package
    href: "../../quick-start/"
atlas_local_contents:
  - label: Support window
    href: "#support-window"
  - label: Choose a path
    href: "#choose-an-integration-path"
  - label: Capability matrix
    href: "#reading-the-matrix"
  - label: Activation
    href: "#install-and-activate-one-capability"
  - label: Queue and PSR boundaries
    href: "#queue-authentication-and-psr-boundaries"
  - label: Release evidence
    href: "#release-boundary"
---

--8<-- "docs/framework-support.md"

For capability-specific mail composition and recipient override behavior, see the [Mail guide](../../components/mail/index.md).
