---
template: atlas-article.html
atlas_article: true
title: SMS
atlas_article_heading_id: sms
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Twilio SDK
atlas_article_context: Application · Adapter
atlas_article_lead: Describe a text or media message in application code, then choose Twilio, logging, or null delivery at the boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: twilio/sdk
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Twilio, logging, or null transport
atlas_relationship_target_label: Application port
atlas_relationship_target: SmsTransport
atlas_relationship_description: Delivery adapters fulfill the portable SMS transport contract
atlas_relationship_caption: Application code composes recipient, sender, body, and media; the adapter owns provider calls and transport failures.
atlas_consequential_label: Privacy and delivery boundary
atlas_consequential_message: A successful send call is provider acceptance, not handset delivery; protect phone numbers and message content and reconcile final status through provider-owned operations.
atlas_next_steps:
  - label: Send a text
    href: "#sending-a-text-message"
  - label: Add media
    href: "#sending-mms-with-media"
  - label: Choose a transport
    href: "#implementations"
  - label: Compose an email
    href: "../mail/"
  - label: Trace delivery safely
    href: "../observability/"
atlas_local_contents:
  - label: SMS message
    href: "#smsmessage"
  - label: Service facade
    href: "#smsservice-facade"
  - label: Transport
    href: "#smstransport"
  - label: Factory
    href: "#smsfactory"
  - label: Configuration
    href: "#symfony-configuration"
  - label: Usage examples
    href: "#usage-examples"
---

SMS separates message composition from provider delivery. Application code owns recipient, sender,
body, and media intent through `SmsMessage`, `SmsFactory`, and `SmsTransport`; Twilio, logging, and null
adapters stay at the boundary. No framework-specific SMS provider is shipped.

--8<-- "docs/sms.md"
