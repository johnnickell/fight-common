---
template: atlas-article.html
atlas_article: true
title: Validation
atlas_article_heading_id: validation
atlas_component_group: Coordinate Application Behavior
atlas_component_owner: Application and Adapter
atlas_component_dependencies: PHP 8.5+, Domain validation utilities, optional Symfony HTTP Kernel and Event Dispatcher
atlas_article_context: Application · Adapter
atlas_article_lead: Validate named application input with portable rules, then optionally apply that contract at a Symfony HTTP boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/http-kernel, symfony/event-dispatcher
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Symfony HTTP adapter
atlas_relationship_source: SymfonyValidationSubscriber
atlas_relationship_target_label: Application service
atlas_relationship_target: ValidationService
atlas_relationship_description: The subscriber reflects controller metadata and delegates named input to the portable validation service
atlas_relationship_caption: HTTP extraction and response policy stay at the adapter boundary; validation rules are usable without Symfony.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Failed rules throw Application ValidationException with all field errors; malformed input or rule definitions throw DomainException instead of being treated as user errors.
atlas_next_steps:
  - label: Model validated concepts
    href: "../values/"
  - label: Coordinate a command
    href: "../messaging/"
  - label: Serialize an application payload
    href: "../serialization/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Overview
    href: "#overview"
  - label: Symfony subscriber
    href: "#wiring-up-the-subscriber"
  - label: Validation attribute
    href: "#the-validation-attribute"
  - label: Rules
    href: "#defining-rules"
  - label: Failure handling
    href: "#handling-validation-errors"
  - label: Complete example
    href: "#complete-example"
---

Validation turns named input and declarative rules into application data without requiring an HTTP
framework. Keep rule selection and validation in a use case or input boundary; compose the optional
Symfony subscriber only where controller metadata is the right boundary.

**Ownership.** `ValidationService`, its data, validators, and the `#[Validation]` attribute are
Application code. `SymfonyValidationSubscriber`, JSON request parsing, exception presentation, and
Symfony service configuration are Adapter concerns. Consumers own their input source, authorization,
error response shape, and any business invariant that needs a Domain value object rather than an
input rule.

**Dependencies.** Portable validation requires PHP 8.5+ and this package. The Symfony controller
subscriber is optional and needs `symfony/http-kernel` plus `symfony/event-dispatcher`.

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with portable application validation.**

```php-inline
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\ValidationService;

$validation = new ValidationService();

try {
    $data = $validation->validate(
        ['email' => 'customer@example.com'],
        [['field' => 'email', 'label' => 'Email', 'rules' => 'required|email']],
    );

    $email = $data->get('email');
} catch (ValidationException $failure) {
    $errors = $failure->getErrors();
}
```

Failed rules collect errors by field and throw `Fight\Common\Application\Validation\Exception\ValidationException`.
An unsupported rule, a malformed rule definition, or input with non-string keys throws
`DomainException`; validation does not sanitize data, authorize the caller, or turn primitives into
Domain values. With Symfony, the Adapter reads query parameters for safe methods and request
parameters otherwise, then lets the same exception propagate before the controller action runs.

## Reference

--8<-- "docs/validation.md"
