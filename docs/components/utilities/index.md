---
template: atlas-article.html
atlas_article: true
title: Utilities
atlas_article_heading_id: utilities
atlas_component_group: Model the Domain
atlas_component_owner: Domain
atlas_component_dependencies: PHP 8.5+, Domain Type contracts
atlas_article_context: Domain
atlas_article_lead: Use small, deterministic helpers for domain support work without making a helper a substitute for a boundary policy.
atlas_article_requires: PHP 8.5+
atlas_article_optional: None
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Domain collection
atlas_relationship_source: HashSet and HashTable
atlas_relationship_target_label: Domain utility
atlas_relationship_target: FastHasher
atlas_relationship_description: Hash collections use FastHasher for bucket selection
atlas_relationship_caption: Utilities support Domain components while remaining framework-neutral and side-effect free.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: FastHasher uses object identity for non-Equatable objects, so those hashes are unsuitable as stable cross-process identifiers.
atlas_next_steps:
  - label: Model values
    href: "../values/"
  - label: Work with collections
    href: "../collections/"
  - label: Compose specifications
    href: "../specifications/"
  - label: Validate application input
    href: "../validation/"
atlas_local_contents:
  - label: Class names
    href: "#classname"
  - label: Fast hashing
    href: "#fasthasher"
  - label: Validation predicates
    href: "#validate"
  - label: Safe printing
    href: "#varprinter"
  - label: Type value
    href: "#type"
---

Utilities provide small domain-support operations: inspect a class name, hash a value, evaluate a
predicate, or print a bounded representation for an error message. Use them behind a meaningful
Domain or boundary policy; a boolean predicate alone does not decide how a use case responds to bad
input.

**Ownership.** `ClassName`, `FastHasher`, `Validate`, and `VarPrinter` live in the Domain utility
namespace. `Type` is a related Domain value in `Fight\Common\Domain\Type`, not a framework service.

**Dependencies.** These helpers require PHP 8.5+ and the package's Domain type contracts. They have
no framework or infrastructure dependency.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with a predicate at a deliberate boundary.**

```php-inline
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Utility\Validate;

function requireUuid(string $input): string
{
    if (!Validate::isUuid($input)) {
        throw new DomainException('A UUID is required.');
    }

    return $input;
}
```

`Validate` methods return `false` for many nonmatching values rather than throwing. `VarPrinter`
is designed for readable diagnostics, not serialization or secret redaction; do not send its output
to logs without applying your application's privacy policy. PHP's `hash()` can reject an unsupported
algorithm passed through `FastHasher::hash()`.

## Reference

--8<-- "docs/utilities.md"
