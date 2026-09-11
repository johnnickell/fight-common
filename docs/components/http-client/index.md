---
template: atlas-article.html
atlas_article: true
title: HTTP Client
atlas_article_heading_id: http-client
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: PSR HTTP contracts, optional Guzzle transport and factories
atlas_article_context: Application · Adapter
atlas_article_lead: Send PSR-7 requests through an application-owned port, then bind Guzzle or a PSR-18 client at the edge.
atlas_article_requires: PHP 8.5+, psr/http-client, psr/http-factory, psr/http-message
atlas_article_optional: guzzlehttp/guzzle, guzzlehttp/promises, guzzlehttp/psr7
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Guzzle transport or PSR-18 compatibility view
atlas_relationship_target_label: Application port
atlas_relationship_target: HttpClient
atlas_relationship_description: Transport adapters fulfill the portable HTTP client port
atlas_relationship_caption: Application services own request intent; the selected adapter owns network I/O and exception translation.
atlas_consequential_label: Security boundary
atlas_consequential_message: A successful transport call does not make a response trusted; set timeouts, validate status and content, and never log credentials or sensitive bodies.
atlas_next_steps:
  - label: Send a portable request
    href: "#basic-get-request"
  - label: Choose an adapter
    href: "#implementations"
  - label: Handle failures
    href: "#exception-hierarchy"
  - label: Sign requests
    href: "../auth/"
  - label: Trace calls safely
    href: "../observability/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: HTTP transport
    href: "#httpclient-transport"
  - label: HTTP facade
    href: "#httpservice-facade"
  - label: Message factories
    href: "#message-factories"
  - label: Promise
    href: "#promise"
  - label: Guzzle adapter
    href: "#guzzle-adapter"
  - label: Logging decorator
    href: "#logginghttpclient"
  - label: Failures
    href: "#exception-hierarchy"
  - label: Installation
    href: "#installation"
  - label: Usage examples
    href: "#usage-examples"
---

HTTP Client keeps outbound request intent in the Application layer and transport details in an Adapter.
Depend on `HttpClient` for sending requests, or on `HttpService` only when the same service also needs
PSR-7 message, stream, and URI factories.

The package requires the PSR contracts. Guzzle packages are optional and needed only for the shipped
Guzzle transport and factories. `Psr18Client` exposes an already configured Fight `HttpClient` through
the PSR-18 client contract; transport discovery, timeouts, TLS trust, proxy, retry, and authentication
policy remain consumer-owned.

--8<-- "docs/http-client.md"
