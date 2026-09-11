---
template: atlas-article.html
atlas_article: true
title: Specifications
atlas_article_heading_id: specifications
atlas_component_group: Model the Domain
atlas_component_owner: Domain
atlas_component_dependencies: PHP 8.5+
atlas_article_context: Domain
atlas_article_lead: Express one business rule per object and compose rules without embedding orchestration in the Domain.
atlas_article_requires: PHP 8.5+
atlas_article_optional: None
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Domain rule
atlas_relationship_source: CompositeSpecification
atlas_relationship_target_label: Composite
atlas_relationship_target: AndSpecification, OrSpecification, NotSpecification
atlas_relationship_description: CompositeSpecification creates logical specification nodes
atlas_relationship_caption: A leaf rule owns its business decision; logical composites assemble it without changing the leaf.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: The candidate is mixed, so each leaf rule must safely reject or guard values outside its own domain type.
atlas_next_steps:
  - label: Model a value
    href: "../values/"
  - label: Select a collection
    href: "../collections/"
  - label: Coordinate a use case
    href: "../messaging/"
  - label: Review domain helpers
    href: "../utilities/"
atlas_local_contents:
  - label: Specification contract
    href: "#the-interface-specification"
  - label: Composite base
    href: "#the-base-compositespecification"
  - label: Write a rule
    href: "#writing-your-first-specification"
  - label: Compose rules
    href: "#composition-examples"
  - label: Best practices
    href: "#best-practices"
---

Specifications keep a Domain decision readable and reusable when it has a name, inputs, or
composition that deserves more than an inline conditional. Implement one leaf rule, then join it
with `and()`, `or()`, or `not()` rather than creating a class for every combination.

**Ownership.** Specifications are pure Domain behavior. An Application use case may choose when to
evaluate one, while adapters supply inputs at a boundary; neither layer belongs inside a
specification.

**Dependencies.** The pattern requires PHP 8.5+ and this package only. It does not require a
container, database, event bus, framework, or adapter.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with one portable rule.**

```php-inline
use Fight\Common\Domain\Specification\CompositeSpecification;

$isPaid = new class extends CompositeSpecification {
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate instanceof Order && $candidate->isPaid();
    }
};

if ($isPaid->and($isReadyToShip)->isSatisfiedBy($order)) {
    // The application can now coordinate shipment.
}
```

`AndSpecification` and `OrSpecification` preserve PHP's short-circuit behavior; `NotSpecification`
inverts the child result. They do not validate the candidate or catch exceptions from a leaf rule,
so make type guards and failure policy explicit in each Domain rule.

## Reference

--8<-- "docs/specifications.md"
