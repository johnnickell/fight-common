# Opt-in safe HTTP error presentation

`SafeJSendErrorMiddleware` is the **explicit opt-in** PSR-15 path for application failures. It does not
replace `JSendErrorMiddleware`, native framework handlers, or MCP/OAuth endpoints. The old PSR-15
middleware still publishes arbitrary `getMessage()` and raw validation errors; keep it operational for
1.x compatibility but do not treat it as a redaction boundary. Migrating legacy defaults requires a
separately approved following-major change after a released-minor deprecation period.

## Wire a request boundary

```php
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Psr17\JSendResponseFactory;
use Fight\Common\Adapter\Middleware\Psr15\SafeJSendErrorMiddleware;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Psr\Log\LoggerInterface;

// $httpFactory implements PSR-17 ResponseFactoryInterface and StreamFactoryInterface.
// Compose this middleware around the chosen PSR-15 handler in your own stack.
$diagnostics = new class ($logger) implements SafeHttpDiagnostics {
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function report(\Throwable $failure): void
    {
        // A minimal deliberately bounded example: only the exception type goes to
        // the logger. For useful causes/traces, redact them in a trusted sink first.
        // Never forward ['exception' => $failure] as PSR-3 context.
        $this->logger->error('HTTP failure', ['type' => $failure::class]);
    }
};
$projector = new class implements SafeHttpProjector {
    public function project(\Throwable $failure, int $status): ?SafeHttpPresentation
    {
        if ($failure instanceof AuthenticationRequired) {
            // The consumer owns scheme, realm, identity and challenge policy.
            return new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Basic realm="example"'));
        }

        return null; // Fixed safe defaults; never copy $failure->getMessage() or getErrors().
    }
};
$errors = new SafeJSendErrorMiddleware(
    new JSendResponseFactory($httpFactory, $httpFactory),
    new SafeHttpErrorPolicy(),
    $diagnostics,
    $projector
);
$response = $errors->process($request, $nextHandler);
```

Place the middleware outside the selected downstream handler. It passes successful responses through unchanged.
Failures thrown outside it, by earlier middleware or native framework dispatch, are not covered. Supply **one**
reporting owner for the stack; do not also log the same exception in another error subscriber. `SafeHttpDiagnostics`
receives a private Throwable once per handled failure. Its implementation must redact before sending data to a
logger, including before a formatter processes PSR-3 context. There is no bundled logger that could silently
forward secrets. If reporting fails, the middleware returns a generic safe 500, does not retry reporting, and
cannot claim the diagnostic was delivered. Projector or primary response-creation failure also falls back to a
safe 500 without recursively reporting. If both primary and fallback PSR factories fail, the middleware throws a
new sanitized `RuntimeException` without a cause; a broken response factory cannot supply a PSR response.

Read `SafeHttpFailure`, `SafeHttpMetadata` and `SafeHttpPresentation` through typed property-name getters:
`status()`, `metadata()`, `challenge()`, `allow()`, `retryAfterSeconds()`, `message()` and `data()` as applicable.
The original public readonly fields remain supported and deprecated in documentation only. See the
[complete getter migration and release window](maintenance/public-property-getters.md). This does not expand
public data or change status/header policy.

## Mapping and public data

| Failure | Status | JSend |
|---|---:|---|
| `AuthenticationRequired` (with a consumer challenge) | 401 | fail |
| `PermissionDenied` | 403 | fail |
| `ResourceNotFound` | 404 | fail |
| `StateConflict` | 409 | fail |
| `ResourceGone` | 410 | fail |
| `PreconditionFailed` | 412 | fail |
| `InvalidInput`, existing structured `ValidationException` | 400 | fail |
| `RuleViolation` | 422 | fail |
| `RateLimitExceeded` | 429 | fail |
| `ServerError`, unknown `Throwable` including PHP `Error` | 500 | error |
| `ServiceUnavailable` | 503 | error |

These categories and consumer subtypes match by declared type, not by code, message or cause. An arbitrary
`ApplicationException` subtype, lookup/auth/storage failure or timeout falls back to 500; no implicit 404/401/504.
A default fail response is `{"status":"fail","data":{"message":"Permission denied."}}` for a 403
(with an analogous fixed message for each status). A default server response is
`{"status":"error","message":"Internal server error."}`. For `ServiceUnavailable` the default error
message is `Service unavailable.`. No diagnostic code or context is included. The field-error map of an
existing `ValidationException` remains private by default; its 400 does **not** imply that all validators are
syntax errors. The application selects `RuleViolation` for an explicitly business-rule 422.

A trusted `SafeHttpProjector` may deliberately select a public `SafeHttpPresentation(message: '...', data: [...])`.
For a fail response, supplied `data` replaces the default `{"message": ...}` entirely; a message without data
replaces that default message. For an error response, `message` is the JSend message and optional data is added
only if supplied. Data must be plain JSON arrays/scalars/null: no arbitrary objects, entities, resources,
`JsonSerializable` callbacks, recursive arrays, invalid UTF-8, non-finite numbers or over-deep data. Construction
copies plain data recursively without retaining PHP array references: later changes to the projector's input
cannot alter an accepted presentation. Invalid data at construction rejects the projection and falls back to
generic 500. Public data safety remains the projector author's responsibility: use
allowlisted, independently authored field messages; never forward exception messages, raw `getErrors()`, causes,
request data or credential-bearing context. There is no client-controlled debug mode or automatic development
exception-detail exposure. Deliberate development diagnostics require a separately trusted project-owned
configuration/path outside this middleware.

## HTTP-only failures and required headers

`SafeHttpFailure` supplies explicit adapter-owned outcomes for bad request (400), authentication (401), method
not allowed (405), not acceptable (406), payload too large (413), unsupported media type (415), too many requests
(429) and unavailable (503). These are not Application exceptions. Use e.g.
`SafeHttpFailure::methodNotAllowed('GET, POST')` or
`SafeHttpFailure::authenticationRequired('Basic realm="example"')` at the transport boundary. Never turn every
framework exception into one of these without an explicit supported mapping. Challenges require a syntactically
valid single scheme/credentials or auth-param list; `Allow` requires comma-and-space-separated HTTP method tokens
(case-sensitive, including extension tokens such as `M-SEARCH`). CR/LF,
controls, invalid values and unexpected headers are rejected. `SafeHttpMetadata` can instead be supplied by the
trusted projector; 401 without a challenge or 405 without Allow fails closed to generic 500, not an apparently
valid but protocol-incomplete response. Only `WWW-Authenticate` on 401, `Allow` on 405 and `Retry-After` on 429/503
are forwarded. No arbitrary exception headers are copied. Retry seconds are nonnegative native integers including
zero and `PHP_INT_MAX`, serialized as decimal text without float conversion. `SafeHttpFailure` retry metadata is
an explicit transport selection; neutral `RateLimitExceeded`/`ServiceUnavailable` hints do **not** become headers
unless the consumer opts into `new SafeHttpErrorPolicy(publishApplicationRetryHints: true)` or deliberately projects
validated retry metadata. A hint does not authorize a retry, transaction replay or a business recovery action.

This package path proves PSR request/response behavior, not installed framework lifecycle, downstream auth,
route selection, diagnostics-sink redaction, rollback, a live application, or a secured deployment.
The [Symfony](#symfony-native-exception-lifecycle) and [Laravel](#laravel-native-report-and-render-lifecycle)
native exception integrations are separate explicit compositions. These new classes/interfaces are additive callable public
API under ADRs 0009–0011; the final concrete classes are constructible but not extensible, the two interfaces
are consumer-implementable, and no legacy constructor, JSend semantic shape or behavior is changed. Security
behavior and header validation are part of the new opt-in contract; legacy arbitrary-message output remains
unsafe but compatible during the minor migration window.

## Symfony native exception lifecycle

Select this **instead of**, not alongside, either canonical
`Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber` or deprecated
`EventSubscriber\SymfonyExceptionSubscriber`. The safe listener is deliberately not an
autoconfigured subscriber or attribute listener. Register only this explicit service and event tag;
exclude legacy exception listeners from application-wide resource scans. Keep the independent
`SymfonyValidationSubscriber` if using attribute validation. Do not also wrap the same path in the
PSR-15 error middleware. For example, in `config/services.yaml` (with default autowiring enabled):

```yaml
services:
    # The consumer supplies a redaction-aware SafeHttpDiagnostics implementation.
    # See the PSR-15 example above: its logger receives only an exception type.
    App\Http\RedactedDiagnostics: ~
    Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics: '@App\Http\RedactedDiagnostics'
    Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy: ~
    Fight\Common\Adapter\Http\Symfony\Controller\SafeErrorController: ~
    Fight\Common\Adapter\Http\Symfony\EventSubscriber\SafeSymfonyExceptionSubscriber:
        tags:
            - { name: kernel.event_listener, event: kernel.exception, method: onKernelException, priority: 64 }
```

The reporting sink must be available. It receives the **original** Throwable once for each selected
JSON error and must redact messages, causes, traces and context *before* a logger/formatter sees them;
do not pass PSR-3 `exception` context. Symfony's `ErrorListener::logKernelException` runs at priority
0 by default: this listener handles JSON first (priority 64), stopping propagation to that logger.
Do not register other higher-priority loggers/exception listeners for these requests. HTML/missing
Accept requests are not intercepted: Symfony's normal logger and HTML error renderer (or the
consumer's replacement) own their reporting and output. Common makes **no** claim that the
non-JSON renderer/logging path is redacted. Verify listener priority and reporting ownership in
your installed application. If the sink fails, JSON falls back to a generic 500 without a second
report attempt; it cannot claim successful logging. Neither a request header nor Symfony `debug`
enables diagnostic disclosure in the safe path. Trusted development reporting belongs outside it.

The listener preserves canonical negotiation: XHR (`X-Requested-With: XMLHttpRequest`) wins;
otherwise `application/json` must be accepted ahead of `text/html` by Symfony quality ordering.
No Accept, HTML preference or other content type delegates. It returns native `JSendResponse`
(`JsonResponse`) through real `kernel.exception` dispatch and does not alter legacy controller,
listener, validation subscriber or service IDs. Do **not** register a framework exception mapping
that rewrites application failures or a competing legacy listener for the safe path. The controller
uses the same `SafeHttpErrorPolicy` and optional `SafeHttpProjector` as PSR-15; wire a trusted
projector explicitly to publish safe field data or supply an application 401 challenge. A missing
challenge or Allow returns generic 500, never a protocol-incomplete 401/405. For native failures,
projection receives the **original** Symfony exception and its translated status. Projections and
metadata must be independently public-safe; raw validation errors are private by default.

Supported native Symfony HTTP failures are `BadRequestHttpException` (400),
`UnauthorizedHttpException` (401), `AccessDeniedHttpException` (403),
`NotFoundHttpException` (404), `MethodNotAllowedHttpException` (405),
`NotAcceptableHttpException` (406), `ConflictHttpException` (409), `GoneHttpException` (410),
`PreconditionFailedHttpException` (412), `UnsupportedMediaTypeHttpException` (415),
`UnprocessableEntityHttpException` (422), `TooManyRequestsHttpException` (429), and
`ServiceUnavailableHttpException` (503), plus an exact `HttpException` at allowlisted
400/401/403/404/405/406/409/410/412/413/415/422/429/502/503/504. Deliberate 413 size and
502/504 gateway outcomes thus stay transport-owned; arbitrary timeouts, HTTP 418 or unknown
subtypes are not mapped. The safe mapper never uses arbitrary integer exception codes or causes.
It takes only `WWW-Authenticate` for 401, `Allow` for 405, and numeric `Retry-After` for 429/503
from native headers; unrelated headers are discarded. Challenge and Allow use shared strict syntax;
Retry-After accepts unsigned canonical decimal seconds through `PHP_INT_MAX`, not HTTP dates.
Missing required or malformed selected metadata, reporting/projection/serialization errors and
unsupported statuses fall back to generic 500 without leaking the native exception's headers.
The consumer owns authentication schemes and retry policy. Symfony framework/kernel listeners may
still alter responses after this listener; test the installed composition, not just direct calls.

These are additive constructible, non-extensible safe types under ADR 0009. Status, JSend shape,
headers, reporting/fallback and negotiation are the new callable behavior under ADRs 0010–0011;
existing unsafe constructors, native response types and disclosures remain compatible during the
minor. Deprecated namespace aliases are still supported but are **not** safe. Default replacement
or removal is reserved for the following major after a released-minor deprecation period. Package
HttpKernel tests prove Common's component-level conformance, **not** a booted installed-package
starter journey or any consumer runtime/security qualification; those remain missing before a new
framework-support claim under ADR 0024.

## Laravel native report and render lifecycle

Opt in by binding Laravel's **exception handler contract** to `SafeLaravelExceptionHandler` in the
consumer's own application bootstrap, *instead of* its existing handler. Do not register this as an
automatic provider, use it alongside another global handler, or install it through Fight Common's
`HttpServiceProvider` (which continues to bind only `JSendResponse`). The new handler extends Laravel's
native `Handler`: in the real HTTP kernel/router pipeline Laravel calls `report()` before `render()`.
The safe handler intercepts reporting **before** Laravel's exception `report()` methods, callbacks,
context construction and default logger can receive raw data. It calls one required consumer-owned
`SafeHttpDiagnostics` sink for every handled Throwable, **including** authentication, authorization,
validation and HTTP exceptions that Laravel normally ignores. It does not call Laravel's normal
`dontReport`/throttling callbacks on this path; configure filtering *inside the redaction-aware sink*
if needed, without forwarding secrets first. A repeated report of the same Throwable instance is
ignored. If the sink throws, JSON rendering returns generic 500 without a second report or false
claim of delivery. The consumer must ensure no earlier middleware, callback, or outer reporting
path logs raw context before this handler; do not register competing exception loggers.

For example, in the consumer's own `bootstrap/app.php` exception setup or service-provider
composition, register the binding **before** serving HTTP requests (adapt any existing handler
registration rather than overwriting it accidentally):

```php
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Laravel\SafeLaravelExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Psr\Log\LoggerInterface;

$app->singleton(SafeHttpDiagnostics::class, function ($app): SafeHttpDiagnostics {
    return new class ($app->make(LoggerInterface::class)) implements SafeHttpDiagnostics {
        public function __construct(private LoggerInterface $logger) {}

        public function report(\Throwable $failure): void
        {
            // Only fixed text and an allowlisted type reach logger formatters.
            $this->logger->error('HTTP failure', ['type' => $failure::class]);
        }
    };
});
$app->singleton(SafeHttpErrorPolicy::class);
$app->singleton(ExceptionHandler::class, SafeLaravelExceptionHandler::class);
// Optional: $app->singleton(SafeHttpProjector::class, ConsumerPublicErrorProjector::class);
```

Laravel's `Request::expectsJson()` chooses the safe native `JSendResponse` for JSON/XHR; non-JSON
requests delegate rendering to Laravel, **not** to a Common HTML sanitizer. Reporting still uses
the consumer's redacted sink. Test your real app's HTML renderer, debug setting, middleware,
exception customizers, and final response handling independently. The safe JSON path never reads
request debug headers or Laravel's `app.debug`; do not expose diagnostic detail unless a separate
trusted, explicitly configured consumer path owns it. Rendering bypasses Laravel's `render()` /
`Responsable` / renderable exception hooks for selected JSON, so do not depend on those hooks for
safe JSON presentation. For JSON customization, wire a `SafeHttpProjector`: it receives the
**original** failure and the translated status, and must return only deliberately public message,
plain data or validated metadata. Never copy native validation messages, `errors()` or a supplied
exception response. `ValidationException` from Common remains a safe fixed 400; Laravel's native
`Illuminate\Validation\ValidationException` with its standard 422 is a separate safe fixed 422.
Mutated native validation statuses are not recognized. Both need explicit public field projection.
A missing application or native 401 challenge returns safe generic 500; supply a consumer-selected
`SafeHttpMetadata(challenge: 'Basic realm="example"')` from a trusted projector or a native
`UnauthorizedHttpException` with a validated challenge. No universal Bearer challenge is invented.

Laravel's deliberately supported native types are `AuthenticationException` (401, challenge
required), `AuthorizationException` (403 or explicit 404 only), native validation (422),
`BadRequestHttpException` (400), `UnauthorizedHttpException` (401),
`AccessDeniedHttpException` (403), `NotFoundHttpException` (404),
`MethodNotAllowedHttpException` (405), `NotAcceptableHttpException` (406),
`ConflictHttpException` (409), `GoneHttpException` (410),
`PreconditionFailedHttpException` (412), `PostTooLargeException` (413),
`UnsupportedMediaTypeHttpException` (415), `UnprocessableEntityHttpException` (422),
`ThrottleRequestsException` / `TooManyRequestsHttpException` (429),
`ServiceUnavailableHttpException` (503), and an **exact** `HttpException` with allowed
400/401/403/404/405/406/409/410/412/413/415/422/429/502/503/504. No generic model/record
lookup, arbitrary authentication fault, authorization status, timeout, exception integer code,
custom HTTP subtype or transformed Laravel `prepareException()` result is trusted implicitly.
Only one scalar `WWW-Authenticate` (401), `Allow` (405), or canonical numeric
`Retry-After` (429/503) is selected from native headers. Invalid/duplicate/injected selected
metadata or absent required challenge/Allow falls back to generic 500. Other native headers
are not forwarded. Native retry metadata is an optional hint, never retry authority. Shared
application failure categories, JSend fail/error shapes, strict public data and retry policy
are described above; Laravel does not alter them.

These additive final classes expose new callable composition/translation behavior under ADRs
0009–0011. Legacy `Adapter\Http\Laravel\Controller\ErrorController` and `HttpServiceProvider` keep their
constructors, response type and **unsafe** raw-message/field-error behavior in this minor;
do not present the old path as safe. Changing legacy defaults requires a separately approved
following-major transition after a released-minor deprecation period. Package booted-Laravel
fixtures prove this native HTTP integration only; downstream installed-package starters,
consumer sink behavior, auth policy, routes and production operations are not qualified by it.
