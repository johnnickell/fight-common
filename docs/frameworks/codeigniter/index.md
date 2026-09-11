---
template: atlas-article.html
atlas_article: true
title: CodeIgniter
atlas_article_heading_id: codeigniter
atlas_component_group: Integration paths
atlas_navigation_label: Framework navigation
atlas_breadcrumb_section: Frameworks
atlas_breadcrumb_group: Integration paths
atlas_component_owner: Adapter and application Config Services
atlas_component_dependencies: codeigniter4/framework, selected capability providers
atlas_article_context: Framework integration · CodeIgniter
atlas_article_lead: Expose selected Fight capabilities through application-owned service delegates while keeping routes, Queue jobs, and provider policy in the project.
atlas_article_requires: PHP 8.5+, CodeIgniter 4.7+
atlas_article_optional: codeigniter4/queue, Symfony Mailer, Twig, Symfony Filesystem
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Application composition root
atlas_relationship_source: Config Services
atlas_relationship_target_label: Selected Fight seam
atlas_relationship_target: Capability delegate
atlas_relationship_description: Application-owned CodeIgniter services call one bounded Fight capability delegate
atlas_relationship_caption: Each delegate translates one capability; no aggregate provider owns unrelated services or application policy.
atlas_consequential_label: Queue ownership
atlas_consequential_message: Queue delivery is at least once; the project owns aliases, topology, retries, failed jobs, workers, and durable outbox policy.
atlas_next_steps:
  - label: Activate services
    href: "#activate-selected-services"
  - label: Configure Queue
    href: "#queue-messaging"
  - label: Review fallbacks
    href: "#proven-fallbacks"
  - label: Compare frameworks
    href: "../framework-support/"
  - label: Open the starter
    href: "https://github.com/johnnickell/project-codeigniter"
atlas_local_contents:
  - label: Ownership
    href: "#ownership-and-installation"
  - label: Activation
    href: "#activate-selected-services"
  - label: Cache and routing
    href: "#native-cache-and-routing"
  - label: Queue messaging
    href: "#queue-messaging"
  - label: HTTP responses
    href: "#native-jsend-response-conversion"
  - label: Fallbacks
    href: "#proven-fallbacks"
  - label: Operations
    href: "#operational-ownership"
---

--8<-- "docs/codeigniter.md"
