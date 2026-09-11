---
template: atlas-article.html
atlas_article: true
title: Collections
atlas_article_heading_id: collections
atlas_component_group: Model the Domain
atlas_component_owner: Domain
atlas_component_dependencies: PHP 8.5+, Domain Utility hashing and validation, Domain Type comparators
atlas_article_context: Domain
atlas_article_lead: Choose a typed collection by its access and ordering semantics, not by a framework container.
atlas_article_requires: PHP 8.5+
atlas_article_optional: None
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Domain collection
atlas_relationship_source: HashTable
atlas_relationship_target_label: Domain utility
atlas_relationship_target: FastHasher
atlas_relationship_description: Hash tables use FastHasher to locate key buckets
atlas_relationship_caption: Collection behavior stays in the Domain and uses small domain utilities for hashing and type checks.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Missing keyed reads throw KeyException, and removing or peeking an empty queue, stack, or deque throws UnderflowException.
atlas_next_steps:
  - label: Model values
    href: "../values/"
  - label: Compose domain rules
    href: "../specifications/"
  - label: Understand hashing and type checks
    href: "../utilities/"
  - label: Persist through repositories
    href: "../repositories/"
atlas_local_contents:
  - label: Helpers
    href: "#helpers"
  - label: Contract architecture
    href: "#contract-architecture"
  - label: ArrayList
    href: "#arraylist"
  - label: Sets and tables
    href: "#hashset"
  - label: Stacks, queues, and deque
    href: "#stacks"
  - label: Comparators
    href: "#comparators"
---

Collections give a Domain model explicit list, set, map, ordering, and queue semantics without
asking it to depend on a framework collection. Select the narrowest contract that expresses the
operation your rule needs, and declare an item or key/value type when the collection has a stable
element type.

**Ownership.** The collection contracts, implementations, comparators, and internal trees and
bucket chains are Domain code. Applications and adapters may consume them but do not own their
behavior or introduce framework collection types into Domain APIs.

**Dependencies.** Collections require PHP 8.5+, the package's Domain type contracts, and its
`FastHasher` and `Validate` utilities. They have no framework dependency.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with the intended access pattern.**

```php-inline
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\HashTable;

$names = ArrayList::of('string');
$names->add('Ada');

$totals = HashTable::of('string', 'int');
$totals->set('order-1001', 42);
$total = $totals->get('order-1001');
```

Declared element types are checked with PHP `assert()`, so consumers must not rely on them as an
input-validation boundary when assertions are disabled. Use explicit validation at a boundary;
handle `KeyException`, `IndexException`, and `UnderflowException` where the selected collection
contract can report absent data.

## Reference

--8<-- "docs/collections.md"
