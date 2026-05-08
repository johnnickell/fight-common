# Validation

The validation system provides declarative, attribute-driven input validation for Symfony controller actions. Rules are declared directly on the controller method; the framework intercepts the request before the action body executes and throws a `ValidationException` if any rule fails. If the action body is reached, the input is guaranteed to be clean.

---

## Table of Contents

1. [Overview](#overview)
2. [Wiring Up the Subscriber](#wiring-up-the-subscriber)
3. [The `#[Validation]` Attribute](#the-validation-attribute)
4. [Defining Rules](#defining-rules)
5. [Custom Error Messages](#custom-error-messages)
6. [Handling Validation Errors](#handling-validation-errors)
7. [Complete Example](#complete-example)

---

## Overview

The system is built from three cooperating pieces:

```
Request
  └─► kernel.controller event
        └─► SymfonyValidationSubscriber
              └─► reads #[Validation] attribute from the controller method
                    └─► ValidationService::validate()
                          ├─► passes  →  controller action executes (input is clean)
                          └─► fails   →  ValidationException thrown (action never executes)
```

**`#[Validation]` attribute** — attached to a controller method; declares the fields to validate and the rules each field must satisfy.

**`SymfonyValidationSubscriber`** — subscribes to `kernel.controller`. For every dispatched request it reflects on the resolved controller method, reads any `#[Validation]` attributes, and runs validation before the action body is entered. Input is drawn from:
- Query string (`$request->query->all()`) for safe HTTP methods (GET, HEAD, OPTIONS).
- Request body (`$request->request->all()`) for state-changing methods (POST, PUT, PATCH, DELETE).

For JSON APIs, register `JsonRequestMiddleware` so that the JSON body is parsed into `$request->request` before the subscriber runs.

**`ValidationService`** — orchestrates field-level validation using the parsed rules. On success it returns an `ApplicationData` object (unused in the attribute flow). On failure it throws `Fight\Common\Application\Validation\Exception\ValidationException`.

---

## Wiring Up the Subscriber

Register `SymfonyValidationSubscriber` as a Symfony service. Because it implements `EventSubscriberInterface`, Symfony's dependency-injection system will automatically tag and wire it when `autoconfigure: true` is enabled (the default in modern Symfony skeletons).

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Fight\Common\Application\Validation\ValidationService: ~

    Fight\Common\Adapter\EventSubscriber\SymfonyValidationSubscriber: ~
```

If you are not using autowiring, provide the dependency explicitly:

```yaml
services:
    Fight\Common\Application\Validation\ValidationService: ~

    Fight\Common\Adapter\EventSubscriber\SymfonyValidationSubscriber:
        arguments:
            - '@Fight\Common\Application\Validation\ValidationService'
        tags:
            - { name: kernel.event_subscriber }
```

For JSON API endpoints, also wrap the kernel with `JsonRequestMiddleware` so that `application/json` request bodies are decoded into `$request->request` before the subscriber inspects them:

```php
// public/index.php (or wherever you build the kernel)
$kernel = new JsonRequestMiddleware($kernel);
```

---

## The `#[Validation]` Attribute

```
Fight\Common\Application\Attribute\Validation
```

The attribute is scoped to methods (`Attribute::TARGET_METHOD`). Apply it to any controller action that requires validated input.

```php
use Fight\Common\Application\Attribute\Validation;

#[Validation(rules: [...])]
public function store(Request $request): JsonResponse { ... }
```

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `rules` | `array` | `[]` | Array of rule-definition arrays. Each element declares one field's validation rules. See [Defining Rules](#defining-rules). |
| `formName` | `?string` | `null` | Reserved for future front-end form integration. Has no effect on validation behavior. |

---

## Defining Rules

Each element of the `rules` array is an associative array describing one field:

```php
[
    'field'  => 'email',            // (required) input key to validate
    'label'  => 'Email',            // (required) human-readable field name used in error messages
    'rules'  => 'required|email',   // (required) pipe-delimited rule string
    'errors' => [                   // (optional) custom error message overrides
        'email' => '%s is not a valid address',
    ],
]
```

### Rule String Format

Rules are joined with `|`. Arguments are placed in square brackets immediately after the rule name:

```
required|alpha_num_dash|min_length[3]|max_length[32]
range_number[0.5, 99.9]
in_list[admin,editor,viewer]
match[/^[a-z0-9-]+$/]
```

Multiple arguments are comma-separated inside the brackets. Whitespace around commas is stripped.

The `match` rule is handled specially: the regex pattern may itself contain `[` and `]` without interfering with the parser.

### Rule Reference

#### Presence

| Rule | Arguments | Description |
|---|---|---|
| `required` | — | Field key must be present in the input. Does not check the value; use `not_empty` or `not_blank` to additionally enforce a non-empty value. |

#### Null & Empty

| Rule | Arguments | Description |
|---|---|---|
| `null` | — | Value must be `null`. |
| `not_null` | — | Value must not be `null`. |
| `empty` | — | Value must satisfy PHP's `empty()` — covers `null`, `false`, `0`, `""`, `"0"`, `[]`. |
| `not_empty` | — | Value must not satisfy PHP's `empty()`. |
| `blank` | — | Value must be blank: an empty string or a string containing only whitespace. |
| `not_blank` | — | Value must not be blank. |

#### Boolean

| Rule | Arguments | Description |
|---|---|---|
| `true` | — | Value must be exactly `true` (strict). |
| `truthy` | — | Value must evaluate to `true` (`!!value`). |
| `false` | — | Value must be exactly `false` (strict). |
| `falsy` | — | Value must evaluate to `false` (`!value`). |

#### Type

| Rule | Arguments | Description |
|---|---|---|
| `scalar` | — | Value must be a PHP scalar: `int`, `float`, `string`, or `bool`. |
| `not_scalar` | — | Value must not be a PHP scalar (e.g., an array or object). |
| `type` | `type` | Value must be an instance of or a primitive of the given type (e.g., `string`, `int`, `App\Dto\Foo`). |
| `list_of` | `type` | Value must be a traversable where every element is of the given type. |
| `numeric` | — | Value must be numeric (satisfies PHP's `is_numeric()`). |
| `natural_number` | — | Value must be a positive integer (greater than zero). |
| `whole_number` | — | Value must be a non-negative integer (zero or greater). |

#### String Format

| Rule | Arguments | Description |
|---|---|---|
| `alpha` | — | Only alphabetic characters (`[a-zA-Z]`). |
| `alpha_dash` | — | Only alphabetic characters, hyphens (`-`), or underscores (`_`). |
| `alpha_num` | — | Only alphanumeric characters (`[a-zA-Z0-9]`). |
| `alpha_num_dash` | — | Only alphanumeric characters, hyphens, or underscores. |
| `digits` | — | Only digit characters (`[0-9]`). |
| `email` | — | Valid email address. |
| `uuid` | — | Valid UUID (any version). |
| `uri` | — | Valid URI. |
| `urn` | — | Valid URN. |
| `ip_address` | — | Valid IP address (v4 or v6). |
| `ip_v4_address` | — | Valid IPv4 address. |
| `ip_v6_address` | — | Valid IPv6 address. |
| `timezone` | — | Valid PHP timezone identifier (e.g., `America/New_York`, `UTC`). |
| `json` | — | Valid JSON-formatted string. |

#### String Content

| Rule | Arguments | Description |
|---|---|---|
| `contains` | `search` | Value must contain the given substring. |
| `starts_with` | `search` | Value must begin with the given substring. |
| `ends_with` | `search` | Value must end with the given substring. |
| `match` | `/pattern/` | Value must match the given regular expression. |

#### String Length

| Rule | Arguments | Description |
|---|---|---|
| `exact_length` | `n` | String must be exactly `n` characters. |
| `min_length` | `n` | String must be at least `n` characters. |
| `max_length` | `n` | String must be at most `n` characters. |
| `range_length` | `min, max` | String length must be between `min` and `max` characters (inclusive). |

#### Numbers

| Rule | Arguments | Description |
|---|---|---|
| `exact_number` | `n` | Value must equal `n` (integer or float). |
| `min_number` | `n` | Value must be greater than or equal to `n`. |
| `max_number` | `n` | Value must be less than or equal to `n`. |
| `range_number` | `min, max` | Value must be between `min` and `max` (inclusive). Accepts integers and floats. |

#### Arrays & Collections

| Rule | Arguments | Description |
|---|---|---|
| `exact_count` | `n` | Collection must contain exactly `n` items. |
| `min_count` | `n` | Collection must contain at least `n` items. |
| `max_count` | `n` | Collection must contain at most `n` items. |
| `range_count` | `min, max` | Collection item count must be between `min` and `max` (inclusive). |
| `in_list` | `a, b, ...` | Value must be one of the listed values. |
| `key_isset` | `key` | Value must be an array that has the given `key` set (value may be `null`). |
| `key_not_empty` | `key` | Value must be an array that has the given `key` set to a non-empty value. |

#### Date & Time

Arguments use PHP date-format characters. The format string is validated at parse time; an invalid format throws a `DomainException`.

| Rule | Arguments | Description |
|---|---|---|
| `date` | `format` | Value must be a valid date matching the given PHP date format (e.g., `Y-m-d`). |
| `time` | `format` | Value must be a valid time matching the given PHP time format (e.g., `H:i:s`). |
| `date_time` | `format` | Value must be a valid date/time matching the given PHP date/time format (e.g., `Y-m-d H:i:s`). |

#### Cross-Field Comparison

These rules compare the field's value against the value of a second field in the same input. The second field is identified by its input key, not its label. If either field is absent from the input, the rule passes (use `required` on both fields if presence is mandatory).

| Rule | Arguments | Description |
|---|---|---|
| `equals` | `field` | Value must be loosely equal (`==`) to the value of the given field. |
| `not_equals` | `field` | Value must not be loosely equal (`!=`) to the value of the given field. |
| `same` | `field` | Value must be strictly identical (`===`) to the value of the given field. |
| `not_same` | `field` | Value must not be strictly identical (`!==`) to the value of the given field. |

---

## Custom Error Messages

Every rule has a default error message that is produced automatically from the field label and any rule arguments. To override any of them, add an `errors` key whose value maps rule names to custom format strings.

The format string follows PHP `sprintf` conventions. The first `%s` is always replaced with the field label. Any subsequent placeholders correspond to the rule's arguments in order.

```php
[
    'field'  => 'password',
    'label'  => 'Password',
    'rules'  => 'required|min_length[12]|max_length[128]|match[/[A-Z]/]',
    'errors' => [
        'min_length' => '%s must be at least %d characters.',
        'max_length' => '%s cannot exceed %d characters.',
        'match'      => '%s must contain at least one uppercase letter.',
    ],
]
```

For rules without arguments (e.g., `required`, `email`), the format string receives only the label:

```php
'errors' => [
    'required' => 'Please provide your %s.',
    'email'    => '%s does not look right.',
],
```

Rules whose messages are not overridden continue to use the system default. Only the specific rule names listed in `errors` are replaced.

---

## Handling Validation Errors

When validation fails, `SymfonyValidationSubscriber` allows the `ValidationException` thrown by `ValidationService` to propagate up the Symfony event chain. The controller action is never entered.

`Fight\Common\Application\Validation\Exception\ValidationException` carries a structured error map:

```php
// $exception->getErrors() returns:
[
    'email'    => ['Email must be a valid email address'],
    'username' => ['Username must be at least 3 characters', 'Username may only contain alphanumeric characters, hyphens, or underscores'],
]
```

Each key is a field name; each value is an array of one or more error message strings for that field (multiple rules can fail independently on the same field — all errors are collected).

The recommended way to handle this exception is a global error controller that subscribes to the `kernel.exception` event and converts it into a `JSendResponse`. This keeps the error-handling logic in one place across the entire application and the controller action stays free of try/catch boilerplate. This pattern will be documented separately once the error controller is implemented.

---

## Complete Example

The following controller creates a user account. Three fields are validated: `email`, `username`, and `role`. The `username` field overrides one default error message.

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Fight\Common\Application\Attribute\Validation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/users', methods: ['POST'])]
    #[Validation(rules: [
        [
            'field' => 'email',
            'label' => 'Email',
            'rules' => 'required|email',
        ],
        [
            'field'  => 'username',
            'label'  => 'Username',
            'rules'  => 'required|alpha_num_dash|min_length[3]|max_length[32]',
            'errors' => [
                'min_length' => '%s must be at least 3 characters long.',
            ],
        ],
        [
            'field' => 'role',
            'label' => 'Role',
            'rules' => 'required|in_list[admin,editor,viewer]',
        ],
    ])]
    public function create(Request $request): JsonResponse
    {
        // If execution reaches here, validation has already passed.
        // All three fields are guaranteed to be present and satisfy their rules.
        $data = $request->request->all();

        // $data['email']    — valid email address
        // $data['username'] — 3–32 alphanumeric/hyphen/underscore characters
        // $data['role']     — one of: admin, editor, viewer

        // ... create the user and return a response

        return new JsonResponse(['status' => 'success', 'data' => null], 201);
    }
}
```

If any rule fails — for example, the submitted `role` is `'superuser'` — the subscriber throws a `ValidationException` before `create()` is entered, with `getErrors()` returning:

```php
[
    'role' => ['Role must be one of [admin,editor,viewer]'],
]
```

The controller body never runs.
