---
template: atlas-article.html
atlas_article: true
title: Values
atlas_article_heading_id: values
atlas_component_group: Model the Domain
atlas_component_owner: Domain and Adapter
atlas_component_dependencies: PHP 8.5+, Domain Utility validation, optional Doctrine DBAL
atlas_article_context: Domain · Adapter
atlas_article_lead: Model immutable domain facts with value equality, then keep persistence mapping at the adapter boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: doctrine/dbal
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Doctrine DBAL type
atlas_relationship_target_label: Domain value
atlas_relationship_target: ValueObject
atlas_relationship_description: Doctrine mapping converts storage values at the adapter boundary
atlas_relationship_caption: Domain values remain framework-free; a Doctrine adapter translates only at persistence edges.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Value equality requires the same concrete type and the same string representation; equal-looking scalars are not value objects.
atlas_next_steps:
  - label: Choose a collection
    href: "../collections/"
  - label: Compose a domain rule
    href: "../specifications/"
  - label: Inspect domain helpers
    href: "../utilities/"
  - label: Persist through a port
    href: "../repositories/"
atlas_local_contents:
  - label: String objects
    href: "#stringobject"
  - label: Structured JSON
    href: "#jsonobject"
  - label: Email and URIs
    href: "#emailaddress"
  - label: UUIDs and identities
    href: "#uuid"
  - label: Doctrine data types
    href: "#doctrine-data-types"
---

Values make a business fact explicit, immutable, and comparable without making it an entity. Use
the named factories at the point where untrusted or primitive input enters your Domain; pass the
resulting value through your use case without coupling it to a framework.

**Ownership.** `Fight\Common\Domain\Value` owns the portable value contracts and concrete values.
The optional Doctrine DBAL mappings live in `Fight\Common\Adapter\Persistence\Doctrine\Type`; no
framework type belongs in a Domain model.

**Dependencies.** Core value objects require PHP 8.5+ and this package. The Doctrine mappings are
optional and require `doctrine/dbal` (and, when used with entities, the consumer's ORM setup).

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with a portable domain value.**

```php-inline
use Fight\Common\Domain\Value\Internet\EmailAddress;

$email = EmailAddress::fromString('customer@example.com');

if ($email->equals(EmailAddress::fromString('customer@example.com'))) {
    $recipient = $email->toString();
}
```

Invalid email, URI, JSON, timezone, and UUID input is rejected by the relevant named factory with
`DomainException`. String indexing can throw `IndexException`, and attempts to write through a
string object's `ArrayAccess` surface throw `ImmutableException`. Do not use a value object as a
mutable record: construct a replacement instead.

## Reference

--8<-- "docs/values.md"
