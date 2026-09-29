# Auth

Three authentication capabilities: **HMAC** for signing and validating HTTP requests,
**Security** for password hashing and JWT token management, and **OAuth** for policy-free resource-server
protection using a strict consumer validator. The Application layer defines reusable contracts and protocol
semantics; adapters provide concrete HTTP integrations. OAuth does not issue tokens or define principals.

```
Application\Auth
├── Authenticator (interface)            — validate(ServerRequestInterface): bool
├── RequestService (interface)           — signRequest(RequestInterface): RequestInterface
├── Security\
│   ├── PasswordHasher (interface)       — hash(string): string
│   ├── PasswordValidator (interface)    — validate(), needsRehash()
│   ├── TokenEncoder (interface)         — encode(array, DateTimeImmutable): string
│   └── TokenDecoder (interface)         — decode(string): array
└── Exception\
    ├── AuthException
    ├── TokenException
    ├── PasswordException
    └── CredentialsException

Adapter\Auth
├── Hmac\
│   ├── HmacAuthenticator               — Authenticator → HMAC request validation
│   ├── HmacRequestService              — RequestService → HMAC request signing
│   ├── HmacKeyGenerator                — generateSecureRandom(int): string
│   └── HmacMethods (trait)             — canonical request string, derived-key signing
└── Security\
    ├── PhpPasswordHasher               — PasswordHasher → password_hash()
    ├── PhpPasswordValidator            — PasswordValidator → password_verify()
    ├── JwtEncoder                      — TokenEncoder → lcobucci/jwt
    └── JwtDecoder                      — TokenDecoder → lcobucci/jwt
```

Authentication verifies identity evidence; authorization remains an application policy. HMAC
validation can optionally consume request nonces through a `Domain\Auth\NonceRepository`. Use a
repository shared by all accepting instances for replay-sensitive traffic. The shipped in-memory
repository is process-local; the Doctrine repository provides a durable shared boundary.

---

## Table of Contents

1. [Authenticator (Interface)](#authenticator-interface)
2. [RequestService (Interface)](#requestservice-interface)
3. [HmacAuthenticator](#hmacauthenticator)
4. [HmacRequestService](#hmacrequestservice)
5. [HmacMethods (Trait)](#hmacmethods-trait)
6. [HmacKeyGenerator](#hmackeygenerator)
7. [PasswordHasher / PasswordValidator (Interfaces)](#passwordhasher-passwordvalidator-interfaces)
8. [PhpPasswordHasher / PhpPasswordValidator](#phppasswordhasher-phppasswordvalidator)
9. [TokenEncoder / TokenDecoder (Interfaces)](#tokenencoder-tokendecoder-interfaces)
10. [JwtEncoder / JwtDecoder](#jwtencoder-jwtdecoder)
11. [Exception Hierarchy](#exception-hierarchy)
12. [Installation](#installation)
13. [Symfony Configuration](#symfony-configuration)
14. [Usage Examples](#usage-examples)
15. [OAuth Resource Server](#oauth-resource-server)

---

## OAuth Resource Server

`Application\Auth\OAuth` supplies metadata, normalized scope/claim values, explicit validation requirements,
consumer ports and resource-server orchestration. `Adapter\Http\OAuth` supplies optional PSR-15/PSR-17 adapters.
There is no OAuth authorization server, key fetcher, JWT validator, route, identity model or permission policy.

### Resource metadata and composition

`OAuthResourceMetadata::fromConfiguration()` requires an exact HTTPS resource identifier, a nonempty ordered
list of unique HTTPS authorization-server issuer identifiers, and an `OAuthScopeSet` of advertised scopes.
Identifiers cannot contain user information or fragments; issuers cannot contain queries. Resource queries are
preserved when deliberately configured. No normalization changes resource or issuer equality. Default metadata
URLs follow RFC 9728: insert `/.well-known/oauth-protected-resource` before the resource path, removing its
terminating slash and preserving its query. An optional explicit HTTPS metadata URL supports challenge-based
publication elsewhere. Consumers must publish the configured document there and preserve the exact resource
identity on any well-known route. Common never selects an issuer or discovers its endpoints.

Metadata JSON contains `resource`, all `authorization_servers` in configured order, `scopes_supported` and
`bearer_methods_supported: ["header"]`. `OAuthMetadataHandler` returns it on GET as HTTP 200
`application/json`; other methods return an empty 405 with `Allow: GET`. Consumers own TLS, trusted ingress,
routing, route-to-resource identity and public access to this metadata route.

```php
use Fight\Common\Adapter\Http\OAuth\OAuthMetadataHandler;
use Fight\Common\Adapter\Http\OAuth\OAuthMiddleware;
use Fight\Common\Adapter\Http\OAuth\OAuthResponseFactory;
use Fight\Common\Application\Auth\OAuth\OAuthResourceMetadata;
use Fight\Common\Application\Auth\OAuth\OAuthResourceServer;
use Fight\Common\Application\Auth\OAuth\OAuthScopeSet;
use Fight\Common\Application\Auth\OAuth\OAuthTokenRequirements;

$metadata = OAuthResourceMetadata::fromConfiguration(
    'https://api.example.com/mcp',
    ['https://auth.example.com'],
    OAuthScopeSet::fromArray(['mcp:read'])
);
$requirements = OAuthTokenRequirements::fromConfiguration(
    $metadata,
    'at+jwt',
    ['RS256'],
    ['https://auth.example.com' => ['current-key', 'rotation-overlap-key']],
    ['sub' => 'string'],
    clockSkewSeconds: 0
);
// Consumer-supplied PSR factories, complete validator, request-scoped handoff and redacting diagnostics.
$responses = new OAuthResponseFactory($responseFactory, $streamFactory);
$metadataHandler = new OAuthMetadataHandler($metadata, $responses);
$resourceServer = new OAuthResourceServer($requirements, $validator, $handoff, $diagnostics);
$protection = new OAuthMiddleware($resourceServer, OAuthScopeSet::fromArray(['mcp:read']), $responses);
// The consumer routes metadata GETs to $metadataHandler, and protected MCP requests through:
$response = $protection->process($request, $guardedMcpEndpoint);
```

Required scopes belong to the current **consumer-composed resource operation**, not a Tool selected by parsing
untrusted JSON. Construct the protection boundary before semantic dispatch. Advertised scopes need not equal
operation requirements; the complete challenged set is authoritative for that operation.

### Strict validation and claims handoff

Implement `OAuthTokenValidator::validate(string $credential, OAuthTokenRequirements $requirements, int $now)`.
The credential is one opaque Bearer value; no request, principal or permission is supplied. Before returning,
validate **every** intrinsic requirement: authenticity/signature with trusted issuer-bound keys; exact accepted
issuer; the configured resource among intended audiences; expiration and not-before; purpose/type; allowed
algorithm; active key identity/rotation; and required claim shape. Key IDs are allowlisted separately for each
issuer, permitting an explicit rotation overlap; they are not URLs to fetch from untrusted token headers.
Consumers own key retrieval, revocation, cryptographic correctness and refreshing request composition when policy
or keys change. `none` is rejected as an allowed algorithm. Expiration and not-before checks cannot be disabled;
clock skew is an explicit integer from 0 through 300 seconds.

Return `OAuthValidatedClaims::fromVerifiedToken($claims, $algorithm, $keyId, $tokenType, $grantedScopes)` **only
after verification**. `$claims` is an immutable `StrictJson` object, normalized to contain `iss` (string), `aud`
(nonempty audience string or list of nonempty strings), `exp` and `nbf` (integer Unix seconds). This bounded
profile requires both timestamps with `nbf < exp`. Additional configured required claims support `string`,
`integer`, `boolean`, `number`, `object` and `list`; absent/null values fail every type. Keep raw tokens and keys
out of claims. This normalized representation also permits consumer-verified opaque credentials; Common does
not parse JWTs or call introspection endpoints.

`OAuthScopeSet::fromArray()` rejects malformed or duplicate RFC 6749 scope tokens and sorts them bytewise.
The validator returns a trusted **effective** scope set: expand issuer-defined scope hierarchies there when a
broader scope implies narrower scopes. Common checks exact, case-sensitive membership without inventing scope
hierarchies or mapping scopes to business permissions. Empty sets are allowed.

The server independently rechecks all normalized issuer, audience, time, purpose, algorithm, active-key and
claim-shape requirements before comparing scopes. It then calls `OAuthClaimsHandoff::accept()` with only the
immutable validated value. The consumer resolves authoritative identity and establishes its authentication
context before returning. No raw header/credential or Common principal enters MCP, Tools, commands or queries.
Use request-scoped composition; consumers own context isolation and cleanup on all exits, including streamed
response lifetimes. A handoff failure denies dispatch with a generic empty 500, not a forged OAuth token failure.
The existing guarded endpoint, Tool availability and business authorization remain in force after handoff.

**Trust boundary:** a public PHP value cannot prove that a consumer performed cryptography. A dishonest or
incomplete validator is not made safe by constructing `OAuthValidatedClaims`. Package evidence proves rejection
propagation, mandatory inputs and independent normalized checks, not qualification of a deployed validator or
issuer. `TokenDecoder`/`JwtDecoder` signature-only decoding is explicitly insufficient and is not accepted as an
`OAuthTokenValidator`. Each consumer must qualify its actual validator and key infrastructure.

### Bearer failures and diagnostics

`OAuthResourceServer::authorize()` accepts a list of Authorization values, current required scopes and an
unsupported-transport indicator. It returns `null` only after successful handoff; an `OAuthFailure` means no
consumer operation may run. Non-PSR consumers must preserve distinct header values, detect unsupported token
transports, honor every result and adapt it through the same response mapping below. An empty list/value or an
unsupported authentication scheme produces a missing-credentials challenge. One case-insensitive `Bearer`
scheme, one or more spaces and one RFC 6750 `b64token` are accepted. Duplicate, coalesced, malformed or ambiguous
Bearer transport is rejected before validation. A syntactically valid but intrinsically malformed token is a
validator rejection, not a transport parse failure.

`OAuthMiddleware` checks raw URI query names as well as parsed query parameters for `access_token` (including
encoded names and bracket forms), rejecting them even alongside a valid header. Form-encoded transport is
unsupported wholesale on this MCP-oriented adapter; it is rejected without reading the potentially unbounded
body. JSON fields are never token sources. Other MCP content-type, Origin, mirror, invocation-guard and protocol
validation remains the existing endpoint's responsibility. Preserve duplicate headers at ingress; irreversibly
collapsed duplicates cannot be recovered by Common.

| Outcome | HTTP | Challenge error | Dispatch |
| --- | --- | --- | --- |
| Missing credentials or unsupported scheme, including HMAC | 401 | Omitted | Never |
| Malformed/ambiguous/unsupported token transport | 400 | `invalid_request` | Never |
| Invalid intrinsic token or incomplete claim evidence | 401 | `invalid_token` | Never |
| Fully validated token lacking current-operation scopes | 403 | `insufficient_scope` | Never |
| Unexpected validation or handoff failure | 500 | No challenge | Never |
| Complete validation, scope comparison and handoff | Downstream | Downstream | Guarded endpoint |

Every OAuth rejection body is empty with `Cache-Control: no-store`, never JSON-RPC or a selected-Tool result.
Every 400/401/403 has one Bearer challenge with `resource_metadata`, all nonempty current-operation scopes and an
optional safely escaped ASCII `realm`. Invalid URLs, unsafe realms or scope configuration fail at composition.
Missing credentials disclose no error code or description. No exception message, token, claim, key, internal
validation reason or consumer permission policy is exposed. `OAuthTokenRejected` is the consumer's fixed,
secret-free intrinsic-failure signal; do not wrap raw validation errors in its public message.

`OAuthDiagnostics::record(OAuthFailure, ?Throwable)` is called once per OAuth rejection. Classified failures
carry no Throwable. Unexpected failures carry the original Throwable to a required consumer-supplied
redaction-aware sink, which must redact messages, argument values, previous exceptions, context and traces
before storage in **every** environment. `SensitiveParameter` helps guard boundary arguments, but cannot redact
arbitrary downstream exception messages or replace that sink. A broken sink is contained to preserve denial
and public safety; it cannot guarantee persisted diagnostics. Failures inside the guarded endpoint remain owned
by `McpDiagnostics`, without a second OAuth diagnostic. PSR request/response infrastructure that cannot read a
request or construct a response remains a consumer runtime failure.

### HMAC coexistence and evidence

Use separate consumer-selected HMAC and Bearer routes by default; Common does not require particular paths.
Other topology is consumer-owned and must keep scheme selection unambiguous. No failed scheme retries the other;
no Bearer path calls `HmacAuthenticator`, consumes an HMAC nonce or uses its key store. On an HMAC route the consumer
retains its existing authenticator and nonce guarantees. Explicitly enforce the selected route's scheme: the
legacy HMAC validator validates its established required headers and signature rather than choosing a scheme.
`Authenticator`, `TokenDecoder`, `JwtDecoder`, HMAC signing and nonce behavior are unchanged.

Direct unit tests cover configuration, extraction, independent claim checks, ordering, immutable values,
challenges, adapter equivalence and diagnostics. `tests/Functional/OAuthMcpJourneyTest.php` exercises a real guarded
MCP endpoint with consumer-validator fakes and handoff/guard/capability spies, metadata, 400/401/403, sanitized 500s,
one diagnostic owner and the separate real HMAC signing/nonce path. This is package conformance evidence, not an
authorization-server, deployment or real-consumer qualification claim.

Authorities: [MCP 2026-07-28 authorization](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization),
[RFC 9728](https://www.rfc-editor.org/rfc/rfc9728), [RFC 6750](https://www.rfc-editor.org/rfc/rfc6750), and
[RFC 8707](https://www.rfc-editor.org/rfc/rfc8707).

## Authenticator (Interface)

`Fight\Common\Application\Auth\Authenticator`

```php-inline
interface Authenticator
{
    /** @throws AuthException */
    public function validate(ServerRequestInterface $request): bool;
}
```

Single implementation: `HmacAuthenticator`.

---

## RequestService (Interface)

`Fight\Common\Application\Auth\RequestService`

```php-inline
interface RequestService
{
    /** @throws CredentialsException */
    public function signRequest(RequestInterface $request): RequestInterface;
}
```

Single implementation: `HmacRequestService`.

---

## HmacAuthenticator

`Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator`

Validates an incoming PSR-7 request by reconstructing its HMAC-SHA256 signature and
comparing it against the `Signature` header. Uses the `HmacMethods` trait.

```php-inline
final class HmacAuthenticator implements Authenticator
{
    public function __construct(
        private string $public,
        string $private,
        private int $timeTolerance,
        private ?NonceRepository $nonceRepository = null,
    ) {}
}
```

| Parameter | Description |
|---|---|
| `$public` | Public key identifier (sent in the `Credential` header) |
| `$private` | Hex-encoded shared secret (converted to binary internally) |
| `$timeTolerance` | Allowed clock skew in seconds for `X-Timestamp` |

### Validation flow

1. **Required headers** — checks `Authorization`, `Credential`, `Signature`, `X-Timestamp`,
   `X-Nonce` are all present. Throws `AuthException` (422) if any are missing.
2. **Timestamp** — validates `X-Timestamp` is within `$timeTolerance` of `REQUEST_TIME`.
   Throws `AuthException` (400) if out of bounds.
3. **Credential** — checks `Credential` header matches `$this->public`. Throws
   `AuthException` (401) on mismatch.
4. **Body content** — if body is non-empty, validates `X-Content-SHA256` header exists
   (422) and matches `sha256(body)` (400).
5. **Signature** — builds the canonical request string via `HmacMethods` and compares it with
   `hash_equals()`. Throws `AuthException` (401) on mismatch.
6. **Replay consumption** — when a `NonceRepository` is supplied, consumes the signed nonce with
   an expiry derived from the request timestamp and tolerance. Duplicate or storage failures are
   wrapped as `AuthException`. Without a repository, timestamp validation alone does not prevent a
   request from being replayed within the tolerance window.

```php-inline
$authenticator = new HmacAuthenticator($publicKey, $privateKey, 300, $nonceRepository);
$valid = $authenticator->validate($serverRequest);
```

---

## HmacRequestService

`Fight\Common\Adapter\Auth\Hmac\HmacRequestService`

Signs an outgoing PSR-7 request with HMAC-SHA256 authentication headers. Uses the
`HmacMethods` trait.

```php-inline
final class HmacRequestService implements RequestService
{
    public function __construct(
        private string $public,
        string $private
    ) {}
}
```

| Parameter | Description |
|---|---|
| `$public` | Public key identifier |
| `$private` | Hex-encoded shared secret (converted to binary internally) |

### Signing flow

1. Normalizes the URI (sorts query parameters alphabetically)
2. Adds headers: `X-Timestamp` (current time), `X-Nonce` (8 random bytes hex), and
   `X-Content-SHA256` (if body is non-empty)
3. Builds canonical request string via `HmacMethods`
4. Creates signature via `HmacMethods` derived-key scheme
5. Adds `Authorization: HMAC-SHA256`, `Credential: {public}`, `Signature: {signature}`
6. Sorts all headers by key and returns the modified request

```php-inline
$service = new HmacRequestService($publicKey, $privateKey);
$signedRequest = $service->signRequest($request);
```

---

## HmacMethods (Trait)

`Fight\Common\Adapter\Auth\Hmac\HmacMethods`

Shared trait used by both `HmacAuthenticator` and `HmacRequestService`.

```php-inline
trait HmacMethods
{
    abstract protected function getSecret(): string;

    protected function normalizeUri(UriInterface $uri): UriInterface;
    protected function createCanonicalRequestString(
        string $method,
        string $authority,
        string $path,
        string $query,
        array $headers
    ): string;
    protected function createSignature(string $canonicalRequest, int $timestamp): string;
}
```

### Canonical Request String

```
{METHOD} {authority}{path}{?query}
{header1}:{value1}
{header2}:{value2}
```

### Derived-Key Signature

The signature uses a three-level HMAC-SHA256 derivation:

```
dateKey    = HMAC-SHA256("HMAC{secret}", YYYY-MM-DD)
signingKey = HMAC-SHA256(dateKey, "signed-request")
signature  = HMAC-SHA256(signingKey, "HMAC-SHA256\n{timestamp}\n{sha256(canonicalRequest)}")
```

The `getSecret()` abstract method returns the binary secret key and must be implemented by
the using class.

---

## HmacKeyGenerator

`Fight\Common\Adapter\Auth\Hmac\HmacKeyGenerator`

Generates cryptographically secure random hex-encoded keys for HMAC authentication.

```php-inline
final class HmacKeyGenerator
{
    /** @throws Exception */
    public static function generateSecureRandom(int $bytes = 16): string;
}
```

Returns `bin2hex(random_bytes($bytes))`. Default 16 bytes produces a 32-character hex
string suitable for use as a public or private HMAC key.

```php-inline
$public  = HmacKeyGenerator::generateSecureRandom();
$private = HmacKeyGenerator::generateSecureRandom(32);  // 64 hex chars
```

---

## PasswordHasher / PasswordValidator (Interfaces)

`Fight\Common\Application\Auth\Security\PasswordHasher`

```php-inline
interface PasswordHasher
{
    /** @throws PasswordException */
    public function hash(string $password): string;
}
```

`Fight\Common\Application\Auth\Security\PasswordValidator`

```php-inline
interface PasswordValidator
{
    public function validate(string $password, string $hash): bool;
    public function needsRehash(string $hash): bool;
}
```

---

## PhpPasswordHasher / PhpPasswordValidator

`Fight\Common\Adapter\Auth\Security\PhpPasswordHasher`

Wraps PHP's native `password_hash()`. Rejects passwords containing null bytes.

```php-inline
final readonly class PhpPasswordHasher implements PasswordHasher
{
    public function __construct(
        private string $algorithm,
        private ?array $options = null
    ) {}
}
```

| Constructor | Example |
|---|---|
| `PhpPasswordHasher(PASSWORD_BCRYPT)` | Default bcrypt cost (10) |
| `PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 12])` | Custom cost |

Throws `PasswordException` if the password contains a null byte.

---

`Fight\Common\Adapter\Auth\Security\PhpPasswordValidator`

Wraps PHP's native `password_verify()` and `password_needs_rehash()`.

```php-inline
final readonly class PhpPasswordValidator implements PasswordValidator
{
    public function __construct(
        private string $algorithm,
        private ?array $options = null
    ) {}
}
```

| Method | Delegates to |
|---|---|
| `validate()` | `password_verify()` |
| `needsRehash()` | `password_needs_rehash()` |

```php-inline
$hasher    = new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 12]);
$validator = new PhpPasswordValidator(PASSWORD_BCRYPT, ['cost' => 12]);

$hash = $hasher->hash('s3cret!');
$validator->validate('s3cret!', $hash);  // true
$validator->needsRehash($hash);           // false (same cost)
```

Laravel applications can instead bind `LaravelPasswordHasher` and `LaravelPasswordValidator` to
Laravel's configured `Illuminate\Contracts\Hashing\Hasher`. These adapters preserve the same Fight
ports; algorithm and rehash policy remain in the Laravel hasher configuration.

---

## TokenEncoder / TokenDecoder (Interfaces)

`Fight\Common\Application\Auth\Security\TokenEncoder`

```php-inline
interface TokenEncoder
{
    /** @throws TokenException */
    public function encode(array $claims, DateTimeImmutable $expiration): string;
}
```

`Fight\Common\Application\Auth\Security\TokenDecoder`

```php-inline
interface TokenDecoder
{
    /** @throws TokenException */
    public function decode(string $token): array;
}
```

---

## JwtEncoder / JwtDecoder

`Fight\Common\Adapter\Auth\Security\JwtEncoder`

Creates signed JWT tokens using `lcobucci/jwt`. Supported algorithms: HS256, HS384, HS512.

```php-inline
final class JwtEncoder implements TokenEncoder
{
    public function __construct(
        string $hexSecret,
        string $algorithm = 'HS256'
    ) {}
}
```

Registered claims (`iss`, `sub`, `aud`, `nbf`, `iat`, `jti`) are extracted from the
`$claims` array and set via the appropriate builder methods. All other claims use
`$builder->withClaim()`. The `exp` claim is set from the `$expiration` parameter.

```php-inline
$encoder = new JwtEncoder($hexSecret, 'HS256');
$token   = $encoder->encode(
    ['sub' => 'user_123', 'role' => 'admin'],
    new DateTimeImmutable('+1 hour')
);
```

---

`Fight\Common\Adapter\Auth\Security\JwtDecoder`

Validates and decodes signed JWT tokens using `lcobucci/jwt`.

```php-inline
final class JwtDecoder implements TokenDecoder
{
    public function __construct(
        string $hexSecret,
        string $algorithm = 'HS256'
    ) {}
}
```

On construction, registers a `SignedWith` constraint. On `decode()`:

1. Parses the JWT string
2. Validates the signature via `SignedWith`
3. Returns all claims via `$token->claims()->all()`
4. Throws `TokenException` on parsing or signature failure

`JwtDecoder` does **not** validate expiration, not-before, issuer, audience, subject, or token ID.
The consuming authentication policy must validate every required claim and time constraint before
trusting the returned array. Decoding is signature verification, not a complete login decision.

```php-inline
$decoder = new JwtDecoder($hexSecret, 'HS256');
$claims  = $decoder->decode($token);
// ['sub' => 'user_123', 'role' => 'admin', 'exp' => ..., ...]
```

---

## Exception Hierarchy

```
SystemException
└── AuthException
    ├── TokenException
    ├── PasswordException
    └── CredentialsException
```

| Exception | Thrown By | Description |
|---|---|---|
| `AuthException` | `Authenticator::validate()` | Authentication failure |
| `TokenException` | `TokenEncoder::encode()`, `TokenDecoder::decode()` | JWT encoding/decoding failure |
| `PasswordException` | `PasswordHasher::hash()` | Password hashing failure (e.g. null byte) |
| `CredentialsException` | `RequestService::signRequest()` | Credential signing failure |

All four are empty exception classes extending `AuthException` which extends
`SystemException`.

---

## Installation

The Auth component itself has no external dependencies beyond PSR-7. Optional adapter
dependencies:

### JWT

```bash
composer require lcobucci/jwt
```

### HMAC

No additional packages — HMAC uses PHP's native `hash_hmac()` and `random_bytes()`.

### Password Hashing

No additional packages — `PhpPasswordHasher` and `PhpPasswordValidator` use PHP's native
`password_hash()` and `password_verify()`.

---

## Symfony Configuration

```yaml
# config/packages/common_auth.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- HMAC Authentication ---
    Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator:
        arguments:
            $public: '%env(HMAC_PUBLIC_KEY)%'
            $private: '%env(HMAC_PRIVATE_KEY)%'
            $timeTolerance: 300

    Fight\Common\Adapter\Auth\Hmac\HmacRequestService:
        arguments:
            $public: '%env(HMAC_PUBLIC_KEY)%'
            $private: '%env(HMAC_PRIVATE_KEY)%'

    # --- Password Hashing ---
    Fight\Common\Adapter\Auth\Security\PhpPasswordHasher:
        arguments:
            $algorithm: !php/const PASSWORD_BCRYPT
            $options:
                cost: 12

    Fight\Common\Adapter\Auth\Security\PhpPasswordValidator:
        arguments:
            $algorithm: !php/const PASSWORD_BCRYPT
            $options:
                cost: 12

    # --- JWT ---
    Fight\Common\Adapter\Auth\Security\JwtEncoder:
        arguments:
            $hexSecret: '%env(JWT_SECRET)%'
            $algorithm: 'HS256'

    Fight\Common\Adapter\Auth\Security\JwtDecoder:
        arguments:
            $hexSecret: '%env(JWT_SECRET)%'
            $algorithm: 'HS256'

    # --- Interface aliases ---
    Fight\Common\Application\Auth\Authenticator:
        alias: Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator

    Fight\Common\Application\Auth\RequestService:
        alias: Fight\Common\Adapter\Auth\Hmac\HmacRequestService

    Fight\Common\Application\Auth\Security\PasswordHasher:
        alias: Fight\Common\Adapter\Auth\Security\PhpPasswordHasher

    Fight\Common\Application\Auth\Security\PasswordValidator:
        alias: Fight\Common\Adapter\Auth\Security\PhpPasswordValidator

    Fight\Common\Application\Auth\Security\TokenEncoder:
        alias: Fight\Common\Adapter\Auth\Security\JwtEncoder

    Fight\Common\Application\Auth\Security\TokenDecoder:
        alias: Fight\Common\Adapter\Auth\Security\JwtDecoder
```

---

## Usage Examples

### HMAC — Signing an Outgoing Request

```php-inline
use Fight\Common\Adapter\Auth\Hmac\HmacRequestService;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleMessageFactory;

$signer  = new HmacRequestService($publicKey, $privateKey);
$factory = new GuzzleMessageFactory();

$request  = $factory->createRequest('POST', '/api/orders', [
    'Content-Type' => 'application/json',
], json_encode(['product' => 'widget']));

$signed = $signer->signRequest($request);

// Now send $signed with any HTTP client
```

### HMAC — Validating an Incoming Request

```php-inline
use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;

$authenticator = new HmacAuthenticator($publicKey, $privateKey, 300);

if (!$authenticator->validate($serverRequest)) {
    // Invalid signature — return 401
}

// Authenticated — proceed
```

### Password Hashing and Verification

```php-inline
use Fight\Common\Adapter\Auth\Security\PhpPasswordHasher;
use Fight\Common\Adapter\Auth\Security\PhpPasswordValidator;

$hasher    = new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 12]);
$validator = new PhpPasswordValidator(PASSWORD_BCRYPT, ['cost' => 12]);

// Registration
$hash = $hasher->hash($plaintextPassword);
// Store $hash in the database

// Login
if (!$validator->validate($plaintextPassword, $storedHash)) {
    throw new RuntimeException('Invalid password');
}

// During login, check if rehashing is needed
if ($validator->needsRehash($storedHash)) {
    $newHash = $hasher->hash($plaintextPassword);
    // Update stored hash
}
```

### JWT — Issue and Validate a Token

```php-inline
use Fight\Common\Adapter\Auth\Security\JwtEncoder;
use Fight\Common\Adapter\Auth\Security\JwtDecoder;

$encoder = new JwtEncoder($hexSecret, 'HS256');
$decoder = new JwtDecoder($hexSecret, 'HS256');

// Issue
$token = $encoder->encode(
    ['sub' => 'user_456', 'role' => 'editor'],
    new DateTimeImmutable('+2 hours')
);

// Validate
try {
    $claims = $decoder->decode($token);
    echo $claims['sub'];  // 'user_456'
} catch (TokenException $e) {
    // Expired, invalid signature, or malformed
}
```
