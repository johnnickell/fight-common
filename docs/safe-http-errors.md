# Opt-in safe HTTP error presentation (PSR-15)

`SafeJSendErrorMiddleware` is the **explicit opt-in** PSR-15 path for application failures. It does not
replace `JSendErrorMiddleware`, Symfony/Laravel handlers, or MCP/OAuth endpoints. The old PSR-15
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
`JsonSerializable` callbacks, recursive arrays, invalid UTF-8, non-finite numbers or over-deep data. A rejected
projection falls back to generic 500. Public data safety remains the projector author's responsibility: use
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
route selection, diagnostics-sink redaction, rollback, a live application, or a secured deployment. The Symfony
and Laravel integrations are separate follow-on work. These new classes/interfaces are additive callable public
API under ADRs 0009–0011; the final concrete classes are constructible but not extensible, the two interfaces
are consumer-implementable, and no legacy constructor, JSend semantic shape or behavior is changed. Security
behavior and header validation are part of the new opt-in contract; legacy arbitrary-message output remains
unsafe but compatible during the minor migration window.
