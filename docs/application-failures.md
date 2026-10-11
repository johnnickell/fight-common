# Application failure vocabulary

`Fight\Common\Application\Error\Exception` provides transport-neutral, opt-in exceptions for
consumer command and query handlers. `ApplicationException` is an abstract `SystemException`, hence
`Catchable` and native `Exception`/`Throwable`; catch a specific category when intent matters, or
`ApplicationException` for the declared family. Every concrete category supports consumer Application
subtypes. An arbitrary new subtype of the base is **not** automatically a recognized category.

| Category | Explicit consumer intent |
|---|---|
| `AuthenticationRequired` | Acceptable authentication is required for this operation. |
| `PermissionDenied` | Permission for this operation was refused. |
| `ResourceNotFound` | The use case intentionally reports a missing resource. |
| `StateConflict` | Current or stale state conflicts with the requested operation. |
| `ResourceGone` | The resource is intentionally reported no longer available. |
| `PreconditionFailed` | An application precondition was not met. |
| `InvalidInput` | Input cannot be used, without a field-error map. |
| `RuleViolation` | A deliberate business-rule rejection. |
| `RateLimitExceeded` | A consumer-defined invocation/admission limit was exceeded. |
| `ServerError` | An explicitly unexpected/server failure, not an expected refusal. |
| `ServiceUnavailable` | An explicitly temporary unavailable outcome. |

For example, a consumer query handler can `throw new ResourceNotFound('private lookup ID', previous: $cause);`
when its own use case decides that absence is a failure. A command handler can throw a consumer subtype of
`PermissionDenied` when its own permission policy refuses an operation. Existing synchronous buses propagate
these exceptions unchanged. Neither construction nor propagation performs a permission check or rolls back
prior effects. Domain-owned decisions and domain-specific exceptions remain in Domain; Domain must not depend
on Application. Consumers choose transactions, authorization, recovery and adoption.

All categories accept the native exception-style `string $message = ''`, `int $code = 0` and
`?Throwable $previous = null`. Messages, integer codes and causes are **diagnostics**, not authorized
public response data; even friendly text, an HTTP-like code, or a PHP `Error` cause remains private.
The code is not an HTTP status. No HTTP handling, status, header, JSend envelope or public-message API is supplied **by these exceptions**.
The separate [opt-in safe PSR-15 error path](safe-http-errors.md) deliberately classifies them in Adapter;
there is no automatic installation or mandatory migration. Existing HTTP paths may still expose
private diagnostics; safe presentation is separate work. A generic `LookupException`, `AuthException`,
storage exception, OAuth outcome or timeout is not implicitly one of these intents. Consumers may
explicitly translate a known outcome while retaining its original cause. MCP Tool failure mappings still
require exact-class registration with separately authored safe constants; inheritance alone does not map.

`RateLimitExceeded` and `ServiceUnavailable` additionally accept a fourth optional
`?int $retryDelaySeconds = null` constructor argument and expose `retryDelaySeconds(): ?int`.
Null is unspecified, zero is valid, and positive integers through `PHP_INT_MAX` retain their exact
value. Negative values throw `DomainException`; native parameter type errors remain native (there is
no blanket `Error` wrapper). The hint neither retries nor schedules anything, and does not authorize
an HTTP Retry-After header by itself. Consumers may deliberately opt into its validated projection through
[the safe PSR-15 policy](safe-http-errors.md). Consumers decide whether/how to use it.

For structured field validation, continue using
`Application\Validation\Exception\ValidationException` and its `fromErrors()`/`getErrors()` contract.
It remains a subtype of Domain `ValidationException`, not of `ApplicationException`. `InvalidInput`
expresses generic unusable-input intent without duplicating field errors; `RuleViolation` expresses
an explicit business rule without requiring validation to be replaced. Existing constructors, causes,
consumer subtype factories and ordinary `ValidationService` behavior remain unchanged.

Compatibility classification (ADRs 0009–0011): these twelve public declarations are additive
next-minor callable types; the eleven concrete types are constructible and extensible, while the
abstract base is extensible but not directly constructible. None is an implementable interface.
Category, inherited catches, native diagnostics and exact retry-hint semantics are behavioral
promises; prose diagnostic text is not a stable/public response. No existing exception hierarchy,
exception output, dependency floor or deprecation is changed. Adoption is optional and does not
qualify downstream workers, databases or HTTP responses.
