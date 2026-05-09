# Value Objects

Value objects are immutable, self-validating domain primitives. They measure, quantify, or describe something in the domain — they are not entities with identity, but rather values that are compared by their content rather than by reference.

All value objects in this library extend `ValueObject`, which implements the `Value` interface (`Equatable` + `JsonSerializable` + `Stringable`). Two value objects are equal when their `toString()` output is identical.

### Recommended: Helper Functions

The recommended way to construct value objects is via the helper functions in `Fight\Common\Domain`. Import with `use function Fight\Common\Domain\{fn};`:

| Helper | Creates | Alias for |
|---|---|---|
| `string($value)` | `StringObject` | `StringObject::create($value)` |
| `mb_string($value)` | `MbStringObject` | `MbStringObject::create($value)` |
| `json_string($value)` | `JsonObject` | `JsonObject::fromString($value)` |
| `json_data($data)` | `JsonObject` | `JsonObject::fromData($data)` |
| `email($address)` | `EmailAddress` | `EmailAddress::fromString($address)` |
| `uri($uri)` | `Uri` | `Uri::fromString($uri)` |
| `url($url)` | `Url` | `Url::fromString($url)` |
| `uuid()` | `Uuid` | `Uuid::comb()` |

Each section below shows both the helper and the direct constructor.

---

## Table of Contents

1. [StringObject](#stringobject)
2. [MbStringObject](#mbstringobject)
3. [JsonObject](#jsonobject)
4. [EmailAddress](#emailaddress)
5. [Uri](#uri)
6. [Url](#url)
7. [Uuid](#uuid)
8. [Identity (UniqueId)](#identity-uniqueid)
9. [Doctrine Data Types](#doctrine-data-types)

---

## StringObject

`Fight\Common\Domain\Value\Basic\StringObject`

A byte-oriented string wrapper with rich manipulation methods. Implements `ArrayAccess`, `Countable`, and `Comparable`.

### Construction

```php
$str = string('hello');                       // helper
$str = StringObject::create('hello');
$str = StringObject::fromString('hello');
```

### Basic Access

```php
$str->value();                               // "hello"
$str->length();                              // 5
$str->isEmpty();                             // false
$str->count();                               // 5
$str->get(1);                                // "e"
$str->has(10);                               // false
$str->chars();                               // ArrayList("h", "e", "l", "l", "o")
```

### Content Checks

```php
$str->contains('ell');                       // true
$str->contains('ELL', caseSensitive: false); // true
$str->startsWith('hel');                     // true
$str->endsWith('lo');                        // true
$str->indexOf('l');                          // 2
$str->lastIndexOf('l');                      // 3
```

### Mutation (always returns new instance)

```php
$str->append(' world');                      // "hello world"
$str->prepend('>> ');                        // ">> hello"
$str->insert(5, '!');                        // "hello!"
$str->surround('*');                         // "*hello*"
$str->trim();                                // removes surrounding whitespace
$str->trimLeft('h');                         // "ello"
$str->trimRight('o');                        // "hell"
$str->pad(7, '-');                           // "-hello--"
$str->padLeft(7, '-');                       // "--hello"
$str->padRight(7, '-');                      // "hello--"
$str->truncate(4, '...');                    // "h..."
$str->truncateWords(8, '...');              // word-aware truncation
$str->repeat(3);                             // "hellohellohello"
$str->replace('l', 'z');                     // "hezzo"
$str->expandTabs(4);                         // replaces tabs with spaces
```

### Substrings

```php
$str->slice(1, 4);                           // "ell"  (between indexes)
$str->substr(0, 3);                          // "hel"  (start + length)
$str->split(' ');                            // ArrayList of StringObject parts
$str->chunk(2);                              // ArrayList("he", "ll", "o")
```

### Case Transforms

```php
$str->toLowerCase();                         // "hello"
$str->toUpperCase();                         // "HELLO"
$str->toFirstLowerCase();                    // "hELLO"
$str->toFirstUpperCase();                    // "Hello"
$str->toCamelCase();                         // "hello"
$str->toPascalCase();                        // "Hello"
$str->toSnakeCase();                         // "hello"
$str->toLowerHyphenated();                   // "hello"
$str->toUpperHyphenated();                   // "HELLO"
$str->toLowerUnderscored();                  // "hello"
$str->toUpperUnderscored();                  // "HELLO"
$str->toSlug();                              // URL-safe slug
```

### ArrayAccess & Iteration

```php
$str[0];                                     // "h"
$str[1] = 'a';                               // throws ImmutableException
isset($str[0]);                              // true

foreach ($str as $char) { /* ... */ }        // iterates characters
```

### Comparison

```php
$str->compareTo(StringObject::create('world')); // negative (natural sort)
```

---

## MbStringObject

`Fight\Common\Domain\Value\Basic\MbStringObject`

Identical API to `StringObject`, but uses multibyte-safe `mb_*` functions with hard-coded UTF-8 encoding. Use this for Unicode strings where character indexes and lengths must account for multi-byte characters.

```php
$mb = mb_string('café');                     // helper
$mb = MbStringObject::create('café');
$mb->length();                               // 4 (not 5)
$mb->get(3);                                 // "é"
$mb->toUpperCase();                          // "CAFÉ"
```

The following methods differ internally:

| Feature | StringObject | MbStringObject |
|---|---|---|
| `length()` | `strlen` | `mb_strlen` |
| `get()` | string offset | `mb_substr` |
| `chars()` | `str_split` | `mb_substr` loop |
| `split()` | `explode` | `preg_split` |
| `chunk()` | `str_split` | `mb_substr` loop |
| `indexOf()` | `strpos`/`stripos` | `mb_strpos`/`mb_stripos` |
| `lastIndexOf()` | `strrpos`/`strripos` | `mb_strrpos`/`mb_strripos` |
| `toCamelCase()` | delegates to `lcfirst` | delegates to `toFirstLowerCase` |
| Case transforms | `strtolower`/`ucfirst` etc. | `mb_strtolower`/`mb_substr` etc. |

Same `ArrayAccess`, `Countable`, and `Comparable` implementations as `StringObject`.

---

## JsonObject

`Fight\Common\Domain\Value\Basic\JsonObject`

Wraps any JSON-encodable data. Validates on construction — throws `DomainException` if the data cannot be encoded.

### Construction

```php
// Helpers
$json = json_data(['user' => 'alice', 'role' => 'admin']);
$json = json_string('{"user":"alice","role":"admin"}');

// Direct
$json = JsonObject::fromData(['user' => 'alice', 'role' => 'admin']);
$json = JsonObject::fromString('{"user":"alice","role":"admin"}');
```

### Output

```php
$json->toString();                           // '{"user":"alice","role":"admin"}'
$json->toData();                             // ['user' => 'alice', 'role' => 'admin']
$json->prettyPrint();                        // pretty-printed JSON with JSON_PRETTY_PRINT
$json->encode(JSON_UNESCAPED_UNICODE);       // custom encoding options
```

Default encoding uses `JSON_UNESCAPED_SLASHES`. Pass custom options to `fromData()` or `encode()`.

---

## EmailAddress

`Fight\Common\Domain\Value\Internet\EmailAddress`

Validates email address format on construction. Throws `DomainException` for invalid addresses.

### Construction

```php
$email = email('alice@example.com');          // helper
$email = EmailAddress::fromString('alice@example.com');
```

### Accessors

```php
$email->toString();                          // "alice@example.com"
$email->localPart();                         // "alice"
$email->domainPart();                        // "example.com"
$email->canonical();                         // "alice@example.com" (lowercased)
```

---

## Uri

`Fight\Common\Domain\Value\Internet\Uri`

Full RFC 3986 URI implementation. Parses, validates, normalizes, and resolves URIs. Implements `Comparable`.

### Construction

```php
// Helper
$uri = uri('https://user:pass@api.example.com:8080/path/to?q=1#frag');

// From a URI string
$uri = Uri::parse('https://user:pass@api.example.com:8080/path/to?q=1#frag');

// From components
$uri = Uri::fromArray([
    'scheme'    => 'https',
    'authority' => 'user:pass@api.example.com:8080',
    'path'      => '/path/to',
    'query'     => 'q=1',
    'fragment'  => 'frag',
]);
```

### Accessors

```php
$uri->scheme();                              // "https"
$uri->authority();                           // "user:pass@api.example.com:8080"
$uri->userInfo();                            // "user:pass"
$uri->host();                                // "api.example.com"
$uri->port();                                // 8080
$uri->path();                                // "/path/to"
$uri->query();                               // "q=1"
$uri->fragment();                            // "frag"
$uri->toArray();                             // all components as array
```

### Immutable Modification

```php
$uri->withScheme('http');                    // new instance, scheme changed
$uri->withAuthority(null);                   // remove authority
$uri->withPath('/new/path');                 // replace path
$uri->withQuery(null);                       // remove query
$uri->withFragment(null);                    // remove fragment
```

### Output

```php
$uri->toString();                            // "https://user:pass@api.example.com:8080/path/to?q=1#frag"
$uri->display();                             // "https://api.example.com:8080/path/to?q=1#frag" (no userinfo)
```

### Relative Reference Resolution

```php
$base = Uri::parse('https://example.com/a/b/c');
$uri  = Uri::resolve($base, 'd/e?q=2');
// result: "https://example.com/a/b/d/e?q=2"
```

### Comparison

```php
$uri->compareTo(Uri::parse('https://other.com')); // natural sort of string representation
```

---

## Url

`Fight\Common\Domain\Value\Internet\Url`

Extends `Uri` with HTTP/HTTPS-specific behavior.

### Restricted Scheme

Only `http` and `https` schemes are accepted:

```php
$url = url('https://example.com/path');      // helper
$url = Url::parse('https://example.com/path');
Url::parse('ftp://example.com');             // throws DomainException
```

### Default Port Removal

Standard ports are omitted: port 80 for `http` and port 443 for `https` are stripped.

```php
$url = Url::parse('https://example.com:443/path');
$url->toString();                            // "https://example.com/path"
$url->port();                                // null
```

### Sorted Query Parameters

Query parameters are sorted by key. Parameters without keys (e.g., `=value`) are dropped.

```php
$url = Url::parse('https://example.com/?z=1&a=2');
$url->query();                               // "a=2&z=1"
```

---

## Uuid

`Fight\Common\Domain\Value\Identifier\Uuid`

RFC 4122 UUID implementation with support for versions 1, 3, 4, and 5. Implements `Comparable`.

### Named Constructors

```php
// Helper — COMB UUID (default, recommended for DB primary keys)
$uuid = uuid();                              // timestamp in MSB
$uuid = uuid(msb: false);                    // timestamp in LSB

// Version 4 — random
$uuid = Uuid::random();

// Version 4 — sequential (COMB)
$uuid = Uuid::comb();                        // timestamp in MSB
$uuid = Uuid::comb(msb: false);              // timestamp in LSB

// Version 1 — time-based
$uuid = Uuid::time();                        // auto-generates node, clock sequence, timestamp

// Version 5 — SHA-1 named
$uuid = Uuid::named(Uuid::NAMESPACE_DNS, 'example.com');

// Version 3 — MD5 named
$uuid = Uuid::md5(Uuid::NAMESPACE_URL, 'https://example.com');
```

### Parsing

```php
$uuid = Uuid::parse('f47ac10b-58cc-4372-a567-0e02b2c3d479');
$uuid = Uuid::fromHex('f47ac10b58cc4372a5670e02b2c3d479');
$uuid = Uuid::fromBytes("\xf4\x7a\xc1\x0b\x58\xcc\x43\x72\xa5\x67\x0e\x02\xb2\xc3\xd4\x79");
$uuid = Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
$uuid = Uuid::fromString('urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479');
```

### Validation

```php
Uuid::isValid('not-a-uuid');                 // false
```

### Accessors

```php
$uuid->timeLow();                            // "f47ac10b"
$uuid->timeMid();                            // "58cc"
$uuid->timeHiAndVersion();                   // "4372"
$uuid->clockSeqHiAndReserved();              // "a5"
$uuid->clockSeqLow();                        // "67"
$uuid->node();                               // "0e02b2c3d479"
$uuid->mostSignificantBits();                // "f47ac10b58cc4372"
$uuid->leastSignificantBits();               // "a5670e02b2c3d479"
```

### Metadata

```php
$uuid->version();                            // 1, 2, 3, 4, or 5 (0 for unknown)
$uuid->variant();                            // VARIANT_RFC_4122 (2) for standard UUIDs
```

### Format Conversion

```php
$uuid->toString();                           // "f47ac10b-58cc-4372-a567-0e02b2c3d479"
$uuid->toUrn();                              // "urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479"
$uuid->toHex();                              // "f47ac10b58cc4372a5670e02b2c3d479"
$uuid->toBytes();                            // 16-byte binary string
$uuid->toArray();                            // associative array of fields
```

### Constants

```php
Uuid::NIL;                                   // "00000000-0000-0000-0000-000000000000"
Uuid::NAMESPACE_DNS;                         // "6ba7b810-9dad-11d1-80b4-00c04fd430c8"
Uuid::NAMESPACE_URL;                         // "6ba7b811-9dad-11d1-80b4-00c04fd430c8"
Uuid::NAMESPACE_OID;                         // "6ba7b812-9dad-11d1-80b4-00c04fd430c8"
Uuid::NAMESPACE_X500;                        // "6ba7b814-9dad-11d1-80b4-00c04fd430c8"
```

---

## Identity (UniqueId)

`Fight\Common\Domain\Identity\UniqueId`

An abstract base class for entity identity types. Wraps a `Uuid` under the hood and provides `generate()`, `fromString()`, equality, and comparison.

### Creating a Typed Identity

To create an identity for a domain entity, extend `UniqueId` with no additional code:

```php
use Fight\Common\Domain\Identity\UniqueId;

final readonly class UserId extends UniqueId {}
```

That is all that is needed. The `UserId` class automatically inherits:

```php
// Generate a new ID
$id = UserId::generate();

// Parse from string
$id = UserId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

// String output
$id->toString();                             // "f47ac10b-58cc-4372-a567-0e02b2c3d479"
(string) $id;                                // "f47ac10b-58cc-4372-a567-0e02b2c3d479"
```

### Identity Safety

Two `UserId` instances with the same UUID are equal; a `UserId` and an `OrderId` with the same UUID are not — the type check prevents cross-entity identity confusion:

```php
$uid = UserId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
$oid = OrderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');

$uid->equals($oid);                          // false (different types)
$uid->equals(UserId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479')); // true
```

### Interface

`UniqueId` implements `Identifier` (which extends `Value` + `Comparable`) and `IdentifierFactory`:

| Method | Description |
|---|---|
| `UserId::generate(): static` | Creates a new random COMB UUID identity |
| `UserId::fromString(string): static` | Parses a UUID string into an identity |
| `$id->toString(): string` | Returns the UUID string |
| `$id->compareTo($other): int` | Natural order comparison |
| `$id->equals($other): bool` | Type-safe equality check |
| `$id->hashValue(): string` | Hash including the type prefix |

---

## Doctrine Data Types

Eleven custom DBAL types in `Fight\Common\Adapter\Doctrine` map domain value objects to SQL
columns, enabling Doctrine ORM to hydrate and dehydrate them directly. Each type extends
`Doctrine\DBAL\Types\Type` and registers under a `common_` prefix.

Most types serialize via `$value->toString()` / `ClassName::fromString()`. The
`MessageDataType` uses `JsonSerializer` instead to support polymorphic message
deserialization through the `Message` interface.

| Type Name | SQL Column | PHP Class | Namespace |
|---|---|---|---|
| `common_uuid` | GUID/UUID | `Uuid` | `Domain\Value\Identifier` |
| `common_email_address` | VARCHAR | `EmailAddress` | `Domain\Value\Internet` |
| `common_uri` | VARCHAR | `Uri` | `Domain\Value\Internet` |
| `common_url` | VARCHAR | `Url` | `Domain\Value\Internet` |
| `common_string` | VARCHAR | `StringObject` | `Domain\Value\Basic` |
| `common_string_text` | TEXT/CLOB | `StringObject` | `Domain\Value\Basic` |
| `common_mb_string` | VARCHAR | `MbStringObject` | `Domain\Value\Basic` |
| `common_mb_string_text` | TEXT/CLOB | `MbStringObject` | `Domain\Value\Basic` |
| `common_json` | JSON | `JsonObject` | `Domain\Value\Basic` |
| `common_type` | VARCHAR | `Type` | `Domain\Type` |
| `common_message` | JSON | `Message` (interface) | `Domain\Messaging` |

### VARCHAR vs TEXT Variants

`StringObject` and `MbStringObject` each provide two mappings depending on expected field
length. Use `common_string` / `common_mb_string` (VARCHAR) for short strings and
`common_string_text` / `common_mb_string_text` (TEXT/CLOB) for large content.

### Usage in an Entity

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'common_uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'common_email_address')]
    private EmailAddress $email;

    #[ORM\Column(type: 'common_string', length: 255)]
    private StringObject $name;

    #[ORM\Column(type: 'common_string_text')]
    private StringObject $biography;
}
```

### Symfony Configuration

Register the types in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        types:
            common_uuid:            Fight\Common\Adapter\Doctrine\UuidDataType
            common_email_address:   Fight\Common\Adapter\Doctrine\EmailAddressDataType
            common_uri:             Fight\Common\Adapter\Doctrine\UriDataType
            common_url:             Fight\Common\Adapter\Doctrine\UrlDataType
            common_string:          Fight\Common\Adapter\Doctrine\StringObjectDataType
            common_string_text:     Fight\Common\Adapter\Doctrine\StringTextDataType
            common_mb_string:       Fight\Common\Adapter\Doctrine\MbStringObjectDataType
            common_mb_string_text:  Fight\Common\Adapter\Doctrine\MbStringTextDataType
            common_json:            Fight\Common\Adapter\Doctrine\JsonObjectDataType
            common_type:            Fight\Common\Adapter\Doctrine\TypeDataType
            common_message:         Fight\Common\Adapter\Doctrine\MessageDataType
```
