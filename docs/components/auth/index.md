---
template: atlas-article.html
atlas_article: true
title: Authentication
atlas_article_heading_id: authentication
atlas_component_group: Connect Systems
atlas_component_owner: Domain, Application, and Adapter
atlas_component_dependencies: PSR HTTP messages, optional lcobucci/jwt or Laravel
atlas_article_context: Domain · Application · Adapter
atlas_article_lead: Verify signed requests, passwords, and tokens through narrow ports while keeping credentials and replay policy at trusted boundaries.
atlas_article_requires: PHP 8.5+, psr/http-message
atlas_article_optional: lcobucci/jwt, laravel/framework
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: HMAC, PHP password API, JWT, or Laravel
atlas_relationship_target_label: Application port
atlas_relationship_target: Authenticator and security contracts
atlas_relationship_description: Security adapters implement portable authentication contracts
atlas_relationship_caption: The application chooses the trust decision; adapters verify protocol-specific evidence without making authorization decisions.
atlas_consequential_label: Replay boundary
atlas_consequential_message: Timestamp tolerance limits request age; replay-sensitive verification must also give HmacAuthenticator a shared durable NonceRepository.
atlas_next_steps:
  - label: Sign an HTTP request
    href: "#hmac-signing-an-outgoing-request"
  - label: Verify an inbound request
    href: "#hmacauthenticator"
  - label: Protect a password
    href: "#passwordhasher-passwordvalidator-interfaces"
  - label: Issue a token
    href: "#tokenencoder-tokendecoder-interfaces"
  - label: Send the request
    href: "../http-client/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Authenticator
    href: "#authenticator-interface"
  - label: Request signing
    href: "#requestservice-interface"
  - label: HMAC validation
    href: "#hmacauthenticator"
  - label: HMAC signing
    href: "#hmacrequestservice"
  - label: Passwords
    href: "#passwordhasher-passwordvalidator-interfaces"
  - label: Tokens
    href: "#tokenencoder-tokendecoder-interfaces"
  - label: Failures
    href: "#exception-hierarchy"
  - label: Installation
    href: "#installation"
  - label: Usage examples
    href: "#usage-examples"
---

Authentication supplies separate contracts for HMAC request verification, password hashing, and token
encoding. These prove identity evidence; they do not decide what the authenticated actor may do.

Keep private HMAC keys and token signing material outside source control, compare signatures through the
shipped verifier, rotate credentials deliberately, and treat decoded JWT claims as untrusted until issuer,
audience, time, and application-specific claims have been accepted by your policy.

--8<-- "docs/auth.md"
