---
template: atlas-article.html
atlas_article: true
title: Serialization
atlas_article_heading_id: serialization
atlas_component_group: Coordinate Application Behavior
atlas_component_owner: Domain and Application
atlas_component_dependencies: PHP 8.5+, Domain Serialization contracts
atlas_article_context: Domain · Application
atlas_article_lead: Let Domain objects own their array state while Application serializers choose a durable string format and reconstruction policy.
atlas_article_requires: PHP 8.5+
atlas_article_optional: None
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Application serializer
atlas_relationship_source: JsonSerializer
atlas_relationship_target_label: Domain contract
atlas_relationship_target: Serializable
atlas_relationship_description: The serializer wraps a Serializable object's array state with its canonical class name
atlas_relationship_caption: Domain objects declare reconstruction; the Application chooses how that envelope crosses a boundary.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Deserialization resolves the envelope's class name and calls its static hydrator; never pass untrusted input to either serializer, especially PhpSerializer.
atlas_next_steps:
  - label: Validate input first
    href: "../validation/"
  - label: Coordinate message delivery
    href: "../messaging/"
  - label: Model immutable values
    href: "../values/"
  - label: Persist event history
    href: "../event-sourcing/"
atlas_local_contents:
  - label: Serializable interface
    href: "#the-serializable-interface"
  - label: Serializer interface
    href: "#the-serializer-interface"
  - label: Envelope format
    href: "#envelope-format"
  - label: JSON serializer
    href: "#jsonserializer"
  - label: PHP serializer
    href: "#phpserializer"
  - label: 1.x compatibility
    href: "#1x-serializer-compatibility"
---

Serialization separates a Domain object's stable array representation from the Application policy
that encodes and reconstructs it. Implement the Domain contracts on objects that can be round-tripped,
then select the canonical Application serializer in the composition root.

**Ownership.** `Serializable` and `Serializer` are Domain contracts. The canonical
`Application\Serialization\JsonSerializer` and `Application\Serialization\PhpSerializer` implement
those contracts. The former `Domain\Serialization` implementations remain only as deprecated 1.x
compatibility surfaces; consumers own format versioning, storage, transport, and migration policy.

**Dependencies.** Serialization requires PHP 8.5+ and this package; no framework package is
required.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with the canonical JSON serializer.**

```php-inline
use Fight\Common\Application\Serialization\JsonSerializer;
use Fight\Common\Domain\Serialization\Serializable;

/** @var Serializable $profile */
$serializer = new JsonSerializer();
$payload = $serializer->serialize($profile);
$restored = $serializer->deserialize($payload);
```

Both formats require an envelope containing `@` (the canonical class name) and `$` (the array
state), and reject a missing envelope or a class that does not implement `Serializable` with
`DomainException`. The class name directs hydration through `arrayDeserialize()`: use only trusted,
authenticated payloads and keep version or allowlist policy in the consuming application.
`PhpSerializer` calls PHP `unserialize()` and is therefore suitable only for trusted PHP-internal
data; prefer JSON for interoperable payloads.

## 1.x serializer compatibility

`Fight\Common\Domain\Serialization\JsonSerializer` and
`Fight\Common\Domain\Serialization\PhpSerializer` remain deprecated compatibility classes in 1.x
and are scheduled for removal in 2.0. New code must import the equivalent
`Fight\Common\Application\Serialization` class; the Domain `Serializable` and `Serializer`
interfaces remain the stable contract.

## Reference

--8<-- "docs/serialization.md"
