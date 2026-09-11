---
template: atlas-article.html
atlas_article: true
title: Sockets
atlas_article_heading_id: sockets
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Symfony Mercure or Laravel broadcasting
atlas_article_context: Application · Adapter
atlas_article_lead: Publish real-time updates through public or private application ports, then bind Mercure or Laravel broadcasting at the edge.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/mercure, laravel/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Mercure hub or Laravel broadcaster
atlas_relationship_target_label: Application port
atlas_relationship_target: Publisher or PrivatePublisher
atlas_relationship_description: Real-time adapters fulfill public and private publication contracts
atlas_relationship_caption: Application code chooses topics and payloads; adapters translate them into the selected real-time transport.
atlas_consequential_label: Privacy and delivery boundary
atlas_consequential_message: Private publication marks an update for transport authorization but does not define subscriber identity, topic entitlement, retention, retry, or proof of receipt.
atlas_next_steps:
  - label: Publish an update
    href: "#publishing-messages"
  - label: Protect a topic
    href: "#private-updates"
  - label: Handle failures
    href: "#error-handling"
  - label: Dispatch durable events
    href: "../messaging/"
  - label: Trace publication
    href: "../observability/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Overview
    href: "#overview"
  - label: Installation
    href: "#installing-the-mercure-component"
  - label: Wiring
    href: "#wiring-up-the-publisher"
  - label: Publishing
    href: "#publishing-messages"
  - label: Private updates
    href: "#private-updates"
  - label: Failures
    href: "#error-handling"
  - label: Complete example
    href: "#complete-example"
---

Sockets is a transport-neutral publication boundary, not a durable event log. `Publisher` publishes
public updates; `PrivatePublisher` adds private publication intent. Mercure and Laravel adapters are
available, but authentication, authorization, retry, retention, and client subscription remain
consumer and operator responsibilities.

--8<-- "docs/sockets.md"
