# Auth

Two authentication subsystems: **HMAC** for signing and validating HTTP requests, and
**Security** for password hashing and JWT token management. The Application layer defines
ports; the Adapter layer provides concrete implementations.

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

---

## Table of Contents

1. [Authenticator (Interface)](#authenticator-interface)
2. [RequestService (Interface)](#requestservice-interface)
3. [HmacAuthenticator](#hmacauthenticator)
4. [HmacRequestService](#hmacrequestservice)
5. [HmacMethods (Trait)](#hmacmethods-trait)
6. [HmacKeyGenerator](#hmackeygenerator)
7. [PasswordHasher / PasswordValidator (Interfaces)](#passwordhasher--passwordvalidator-interfaces)
8. [PhpPasswordHasher / PhpPasswordValidator](#phppasswordhasher--phppasswordvalidator)
9. [TokenEncoder / TokenDecoder (Interfaces)](#tokenencoder--tokendecoder-interfaces)
10. [JwtEncoder / JwtDecoder](#jwtencoder--jwtdecoder)
11. [Exception Hierarchy](#exception-hierarchy)
12. [Installation](#installation)
13. [Symfony Configuration](#symfony-configuration)
14. [Usage Examples](#usage-examples)

---

## Authenticator (Interface)

`Fight\Common\Application\Auth\Authenticator`

```php
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

```php
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

```php
final class HmacAuthenticator implements Authenticator
{
    public function __construct(
        private string $public,
        string $private,
        private int $timeTolerance
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
5. **Signature** — builds canonical request string via `HmacMethods`, computes expected
   signature, returns `true` on match or `false` on mismatch (no exception).

```php
$authenticator = new HmacAuthenticator($publicKey, $privateKey, 300);
$valid = $authenticator->validate($serverRequest);
```

---

## HmacRequestService

`Fight\Common\Adapter\Auth\Hmac\HmacRequestService`

Signs an outgoing PSR-7 request with HMAC-SHA256 authentication headers. Uses the
`HmacMethods` trait.

```php
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

```php
$service = new HmacRequestService($publicKey, $privateKey);
$signedRequest = $service->signRequest($request);
```

---

## HmacMethods (Trait)

`Fight\Common\Adapter\Auth\Hmac\HmacMethods`

Shared trait used by both `HmacAuthenticator` and `HmacRequestService`.

```php
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

```php
final class HmacKeyGenerator
{
    /** @throws Exception */
    public static function generateSecureRandom(int $bytes = 16): string;
}
```

Returns `bin2hex(random_bytes($bytes))`. Default 16 bytes produces a 32-character hex
string suitable for use as a public or private HMAC key.

```php
$public  = HmacKeyGenerator::generateSecureRandom();
$private = HmacKeyGenerator::generateSecureRandom(32);  // 64 hex chars
```

---

## PasswordHasher / PasswordValidator (Interfaces)

`Fight\Common\Application\Auth\Security\PasswordHasher`

```php
interface PasswordHasher
{
    /** @throws PasswordException */
    public function hash(string $password): string;
}
```

`Fight\Common\Application\Auth\Security\PasswordValidator`

```php
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

```php
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

```php
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

```php
$hasher    = new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 12]);
$validator = new PhpPasswordValidator(PASSWORD_BCRYPT, ['cost' => 12]);

$hash = $hasher->hash('s3cret!');
$validator->validate('s3cret!', $hash);  // true
$validator->needsRehash($hash);           // false (same cost)
```

---

## TokenEncoder / TokenDecoder (Interfaces)

`Fight\Common\Application\Auth\Security\TokenEncoder`

```php
interface TokenEncoder
{
    /** @throws TokenException */
    public function encode(array $claims, DateTimeImmutable $expiration): string;
}
```

`Fight\Common\Application\Auth\Security\TokenDecoder`

```php
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

```php
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

```php
$encoder = new JwtEncoder($hexSecret, 'HS256');
$token   = $encoder->encode(
    ['sub' => 'user_123', 'role' => 'admin'],
    new DateTimeImmutable('+1 hour')
);
```

---

`Fight\Common\Adapter\Auth\Security\JwtDecoder`

Validates and decodes signed JWT tokens using `lcobucci/jwt`.

```php
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
4. Throws `TokenException` on any failure (invalid signature, expired token, malformed
   string, etc.)

```php
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

```php
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

```php
use Fight\Common\Adapter\Auth\Hmac\HmacAuthenticator;

$authenticator = new HmacAuthenticator($publicKey, $privateKey, 300);

if (!$authenticator->validate($serverRequest)) {
    // Invalid signature — return 401
}

// Authenticated — proceed
```

### Password Hashing and Verification

```php
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

```php
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