Value objects are immutable, self-validating domain primitives. They measure, quantify, or describe something in the domain — they are not entities with identity, but rather values that are compared by their content rather than by reference.

All value objects in this library extend `ValueObject`, which implements the `Value` interface (`Equatable` + `JsonSerializable` + `Stringable`). Two value objects are equal only when they have the same concrete type and an identical `toString()` representation of their attributes.

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

Sections show helpers where available and named factories; the IP hierarchy has no helper.

---

## Table of Contents

1. [StringObject](#stringobject)
2. [MbStringObject](#mbstringobject)
3. [JsonObject](#jsonobject)
4. [StrictJson](#strictjson)
5. [EmailAddress](#emailaddress)
6. [IP addresses](#ip-addresses)
7. [Uri](#uri)
8. [Url](#url)
9. [Uuid](#uuid)
10. [Identity (UniqueId)](#identity-uniqueid)
11. [StreamId](#streamid)
12. [Doctrine Data Types](#doctrine-data-types)

---

## StringObject

`Fight\Common\Domain\Value\Basic\StringObject`

A byte-oriented string wrapper with rich manipulation methods. Implements `ArrayAccess`, `Countable`, and `Comparable`.

### Construction

```php-inline
$str = string('hello');                       // helper
$str = StringObject::create('hello');
$str = StringObject::fromString('hello');
```

### Basic Access

```php-inline
$str->value();                               // "hello"
$str->length();                              // 5
$str->isEmpty();                             // false
$str->count();                               // 5
$str->get(1);                                // "e"
$str->has(10);                               // false
$str->chars();                               // ArrayList("h", "e", "l", "l", "o")
```

### Content Checks

```php-inline
$str->contains('ell');                       // true
$str->contains('ELL', caseSensitive: false); // true
$str->startsWith('hel');                     // true
$str->endsWith('lo');                        // true
$str->indexOf('l');                          // 2
$str->lastIndexOf('l');                      // 3
```

### Mutation (always returns new instance)

```php-inline
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
$str->truncateWords(8, '...');               // word-aware truncation
$str->repeat(3);                             // "hellohellohello"
$str->replace('l', 'z');                     // "hezzo"
$str->expandTabs(4);                         // replaces tabs with spaces
```

### Substrings

```php-inline
$str->slice(1, 4);                           // "ell"  (between indexes)
$str->substr(0, 3);                          // "hel"  (start + length)
$str->split(' ');                            // ArrayList of StringObject parts
$str->chunk(2);                              // ArrayList("he", "ll", "o")
```

### Case Transforms

```php-inline
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

```php-inline
$str[0];                                     // "h"
$str[1] = 'a';                               // throws ImmutableException
isset($str[0]);                              // true

foreach ($str as $char) { /* ... */ }        // iterates characters
```

### Comparison

```php-inline
$str->compareTo(StringObject::create('world')); // negative (natural sort)
```

---

## MbStringObject

`Fight\Common\Domain\Value\Basic\MbStringObject`

Identical API to `StringObject`, but uses multibyte-safe `mb_*` functions with hard-coded UTF-8 encoding. Use this for Unicode strings where character indexes and lengths must account for multi-byte characters.

```php-inline
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

```php-inline
// Helpers
$json = json_data(['user' => 'alice', 'role' => 'admin']);
$json = json_string('{"user":"alice","role":"admin"}');

// Direct
$json = JsonObject::fromData(['user' => 'alice', 'role' => 'admin']);
$json = JsonObject::fromString('{"user":"alice","role":"admin"}');
```

### Output

```php-inline
$json->toString();                           // '{"user":"alice","role":"admin"}'
$json->toData();                             // ['user' => 'alice', 'role' => 'admin']
$json->prettyPrint();                        // pretty-printed JSON with JSON_PRETTY_PRINT
$json->encode(JSON_UNESCAPED_UNICODE);       // custom encoding options
```

Default encoding uses `JSON_UNESCAPED_SLASHES`. Pass custom options to `fromData()` or `encode()`.

### Immutable JSON snapshots

Contract ID: `fight-common.behavior.json-object-snapshots`.

Opt in with `JsonObject::fromSnapshot(mixed $data, int $encodingOptions = JSON_UNESCAPED_SLASHES)` or
`JsonObject::fromSnapshotString(string $value, int $encodingOptions = JSON_UNESCAPED_SLASHES)`. Both return the
existing JsonObject type. These additive public factories are callable/constructible API under ADR 0009; they
introduce no extensibility, new dependency, database type or mandatory migration.

```php-inline
$input = (object) ['child' => (object) ['value' => 'original'], 'ratio' => 1.0];
$snapshot = JsonObject::fromSnapshot($input);
$input->child->value = 'changed';
$snapshot->toData()->child->value = 'also changed';
$snapshot->toString(); // {"child":{"value":"original"},"ratio":1.0}
$restored = JsonObject::fromSnapshotString($snapshot->toString());
$restored->equals($snapshot); // true
```

Capture encodes scalars, arrays, ordinary objects and supported `JsonSerializable` output at construction, then
verifies that object-mode decoding succeeds. Only captured JSON is retained, not the supplied object graph.
Every `toData()` and `jsonSerialize()` returns an independent object-mode reconstruction: objects (including empty
and numerically named objects) stay objects, lists stay arrays, and scalars/null keep their kinds. Nested input
objects, PHP references and earlier mutable outputs cannot alter the retained string, hash or equality against an
unchanged comparison value. Custom serializers run at capture, never again on reads. This does not promise one
callback per object identity when references repeat, retain class identity/methods or reference topology, deep-clone
arbitrary PHP state, perform redaction, or establish permission to publish the data. Private/non-JSON state is not
captured. Common does not sandbox or roll back consumer serializer effects.

**Numbers and text.** Snapshot-owned encoding temporarily selects PHP `serialize_precision=-1` (shortest native
float round-trip spelling), restores the previous setting on success/failure, and always includes
`JSON_PRESERVE_ZERO_FRACTION`. Native integers and finite floats remain distinct (`1` versus `1.0`, including
negative float zero); changing ambient formatting later cannot change retained string/equality/hash or snapshot-owned
`encode()`/`prettyPrint()` output. Consumers own any global-setting changes inside their serializers. No arbitrary-
precision decimal or recovery of precision already lost in a caller's float is promised.

Text reconstruction uses PHP's native integer range (`PHP_INT_MIN` through `PHP_INT_MAX`) and native float conversion.
Every integer literal outside that range rejects, even in a subsequently overwritten duplicate property; numeric
strings and property names remain strings. Decimal/exponent literals become native floats: excess decimal digits
round, underflow can become `0.0`, and overflow to infinity rejects, including literals in overwritten duplicate
properties or discarded subtrees. Float overflow uses the same fixed encoding failure and `JSON_ERROR_INF_OR_NAN`
cause whether retained or discarded. Whitespace, escapes, exponent spelling and decimal lexemes normalize;
`1e0` becomes `1.0` and integer `-0` becomes `0`. Ordinary PHP JSON duplicate-name decoding keeps
the last value. This is not preservation of arbitrary input text or general JSON canonicalization.

**Presentation options.** The allowed bitmask is zero or any combination of `JSON_HEX_TAG`, `JSON_HEX_AMP`,
`JSON_HEX_APOS`, `JSON_HEX_QUOT`, `JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE`,
`JSON_UNESCAPED_LINE_TERMINATORS`, `JSON_PRETTY_PRINT` and `JSON_PRESERVE_ZERO_FRACTION`.
Line terminators are unescaped only together with unescaped Unicode, as in PHP. Zero-fraction preservation is always
on. Other bits reject, including `JSON_FORCE_OBJECT`, `JSON_NUMERIC_CHECK`, partial-output/invalid-UTF-8 substitution
or omission, and `JSON_THROW_ON_ERROR` (the snapshot owns error handling). Integer constants with identical numeric
bits cannot be distinguished by their names; use encoding constants, not decoder flags.

The same option boundary applies to snapshot `encode()`. `prettyPrint()` adds pretty printing to the captured options;
`encode()` uses its explicit/default options without replacing the retained representation. Factory options can
change equality/hash because inherited ValueObject equality compares strings, including property order and formatting.
No key sorting or semantic/key-order-independent equality is introduced. A snapshot and a legacy value with identical
strings compare equal and have equal hashes; a separately mutable legacy comparator may subsequently change.

Outer `json_encode($snapshot, ...)` receives fresh data, **not raw captured JSON**. The outer encoder controls precision,
flags and total depth, including wrappers: use suitable precision and `JSON_PRESERVE_ZERO_FRACTION` when float kinds
matter. Its lossy flags/defaults can change external output but cannot mutate the snapshot. Snapshot factory presentation
options do not override outer encoding options.

**Limits and failures.** Both codecs use depth 512. At most **511 nested containers** reconstruct, including empty
containers: in root-zero node terminology, containers stop at depth 510 and scalars may reach 511. Encoder-only
acceptance at 512 containers is insufficient and rejects at the independent decoder boundary. Malformed JSON/Unicode,
resources, cycles, non-finite floats and object property names beginning with U+0000 reject; empty names and non-leading
U+0000 are supported. No partial/error-substituted snapshot is returned. Codec failures throw catchable
`DomainException` with fixed messages `Unable to encode JSON snapshot.` or `Unable to decode JSON snapshot.` and a
`JsonException` cause carrying the codec error code. Integer-range rejection uses
`Unsupported JSON snapshot representation.`; unsupported option bits use `Unsupported JSON snapshot encoding options.`.
Those validation failures have no underlying codec cause. Consumer serializer throwables, including `JsonException`
and PHP `Error`, propagate unchanged with original identity, code and previous cause. Fixed wrapper messages do not
make arbitrary consumer exceptions or diagnostic causes public-safe.

### Legacy JSON and Doctrine boundaries

Contract ID: `fight-common.behavior.json-object-snapshot-doctrine` (controlled adapter translation evidence).

`fromData()`, `fromString()`, `json_data()` and `json_string()` retain their existing legacy behavior in this minor.
Legacy construction can retain nested mutable objects/references, expose them from accessors and reinvoke custom
serializers on reads. Legacy associative text decoding/encoding collapses `{}` to `[]`, `{"0":"zero"}` to `["zero"]`
and `1.0` to `1`. These limitations explain why snapshot reconstruction has its own factory; they are not repaired or
silently replaced. Existing StrictJson/MCP semantics remain independent and unchanged.

Both canonical and deprecated Doctrine `JsonObjectDataType` identities write a snapshot via its captured string.
Hydration from database text still calls legacy `fromString()`: it does **not** restore snapshot mode or faithfully
round-trip all snapshot shapes/numeric representations. Applications needing snapshot restoration must explicitly
use the snapshot-aware text boundary in their own composition; no automatic Doctrine opt-in, persisted-data rewrite
or schema migration is supplied. Existing supported stored data remains readable. Adapter fixtures prove translation
against controlled platforms, not real database JSON storage/normalization or downstream qualification.

This is an additive minor capability under ADRs 0009–0011: existing public construction, helper semantics, exception
paths, Doctrine identities and hydration remain unchanged. Snapshot-specific exceptions, fresh mutable output,
numeric representation and string equality are explicit new promises, not a claim that signatures alone establish
compatibility or that legacy JSON becomes immutable.

---

## StrictJson

`Fight\Common\Domain\Value\Basic\StrictJson`

An immutable, framework-free JSON value that preserves objects, lists and scalar types. Unlike `JsonObject`,
it validates a bounded plain-data tree and never exposes mutable generic objects through data accessors.
Legacy `JsonObject` factories and helpers are unchanged; its opt-in snapshots above are a separate capability.
StrictJson supplies no Doctrine mapping or new global helper.

```php-inline
use Fight\Common\Domain\Value\Basic\StrictJson;

$value = StrictJson::fromString('{"options":{},"items":[],"limit":1.0,"cursor":null}');
$emptyObject = StrictJson::fromObject();      // {}
$emptyList = StrictJson::fromData([]);        // []
$value->get('options')->isObject();           // true
$value->get('items');                        // []
$value->has('cursor');                       // true (explicit null)
$value->has('missing');                      // false
$value->get('missing');                      // null
$next = $value->with('limit', 2);             // new object; $value is unchanged
$value->toString();                          // retains {}, [], 1.0 and null
```

`fromData()` accepts null, Booleans, integers, finite floats, valid Unicode strings, PHP lists, associative arrays,
existing `StrictJson` values and exact plain PHP decoded objects. Arbitrary objects, subclasses of generic objects,
resources and consumer serializers reject without invoking serialization code. Associative arrays become objects;
use `fromObject($properties)` to require an object for empty or numerically named properties. Numeric PHP array keys
remain their original JSON property names on serialization, including when replaced through `with()`.

`properties()` returns an object's property map; `get()`, `has()` and `with()` require an object and otherwise throw
`DomainException`. Nested objects are immutable `StrictJson` values, lists are ordinary arrays, and scalars retain
their types. `toData()` returns that same representation: the value itself for an object, or its list/scalar data.
It is not an associative-array decoder that collapses `{}` into `[]`. `jsonSerialize()` is the encoding boundary:
any generic object it emits is a fresh representation, never retained mutable state.

Construction rejects malformed JSON/Unicode, non-finite numbers, cycles and nesting beyond `maxDepth` (default 64,
root depth zero), including depth introduced by composing existing values. All factories and `with()` accept this
optional limit from 0 through 511. The retained PHP decoder depth of 512 permits at most 511 nested containers:
scalars may occupy node depth 511, but objects and lists (including empty ones) must stop at depth 510. Construction
and replacement enforce that codec ceiling as well as `maxDepth`, so accepted values reconstruct from their emitted
JSON under the same limit. MCP uses 511 for the protocol envelope and 64 separately for Tool arguments, schemas and
output, so wrappers do not consume the Tool budget. Property names beginning with U+0000 reject because PHP's
object-mode decoder cannot represent them; empty names and embedded non-leading U+0000 are supported. Exceptions use
fixed messages without reflecting data. PHP-decoded number precision is not recovered. Encoding preserves zero-fraction
floats. Equality/hash follow the existing ValueObject string-representation contract: property order and `1` versus
`1.0` remain significant; this is not JSON Schema's numeric-aware structural equality. Validation is not redaction.

---

## EmailAddress

`Fight\Common\Domain\Value\Internet\EmailAddress`

Validates email address format on construction. Throws `DomainException` for invalid addresses.

### Construction

```php-inline
$email = email('alice@example.com');          // helper
$email = EmailAddress::fromString('alice@example.com');
```

### Accessors

```php-inline
$email->toString();                          // "alice@example.com"
$email->localPart();                         // "alice"
$email->domainPart();                        // "example.com"
$email->canonical();                         // "alice@example.com" (lowercased)
```

### Email parts and identity

**Contract ID:** `fight-common.behavior.email-parts-and-identity`

Part extraction uses the separator after the complete accepted local part. Local quotes and escape bytes are
retained, not decoded; domain-literal brackets are removed, but literal contents (including `IPv6:`) are preserved.

```php-inline
$quoted = EmailAddress::fromString('"a@b"@Example.COM');
$quoted->localPart();                        // '"a@b"'
$quoted->domainPart();                       // 'Example.COM'
$quoted->toString();                         // '"a@b"@Example.COM'
$quoted->canonical();                        // '"a@b"@example.com'

$literal = EmailAddress::fromString('"a@b"@[IPv6:2001:db8::1]');
$literal->domainPart();                       // 'IPv6:2001:db8::1'
```

This corrects extraction for already accepted quoted addresses; it does not broaden `Validate::isEmail` syntax.
String/JSON representation and hashing retain the original address, and equality remains concrete-type and
case-sensitive. `canonical()` lowercases the **whole** address as a separate operation; it does not change the
stored value or define login identity. The canonical and legacy Doctrine email types retain original stored text,
the `common_email_address` name, null/empty conversion and instance passthrough; no data rewrite is required.

Validation is lexical only: no DNS lookup, reachability or ownership verification occurs. Consumers own login
identity, authorization and permission to contact the address; neither accepted syntax nor diagnostic text grants
those guarantees or promises public-safe presentation.

---

## IP addresses

`Fight\Common\Domain\Value\Internet\IpAddress` is an abstract readonly family boundary. Its generic
`fromString()` returns final readonly `IpV4Address` or `IpV6Address`; concrete factories accept only their own
family. These are additive, opt-in values; existing `Validate::isIp*` predicates, Application `IsIp*` rules and
URI/adapter callers retain their existing behavior and are not migrated automatically. No Doctrine mapping,
Identifier, ordering or consumer-extension contract is introduced by the abstract declaration.

### IP literal normalization and family identity

**Contract ID:** `fight-common.behavior.ip-address-values`

Factories require a bare literal string, without trimming, resolving hostnames or inferring context. Empty or
malformed values, ambiguous leading-zero IPv4, whitespace, CIDR, brackets, IPv4 ports and IPv6 zone identifiers
raise `DomainException`. A valid final IPv6 hextet is address content, not a port; use a separate connection/URI
boundary for ports. Native PHP parameter typing remains distinct from invalid-string value validation.

Canonical IPv4 is dotted decimal. Canonical IPv6 uses lowercase hexadecimal hextets without leading zeros,
compressing the **first longest run of at least two zero hextets** to `::`; an isolated zero is never compressed.
Every embedded IPv4 suffix, including mapped IPv6, is rendered as hexadecimal hextets rather than dotted decimal.
Common owns this formatting policy, using native binary parsing but not platform-specific native rendering.

```php-inline
use Fight\Common\Domain\Value\Internet\IpAddress;
use Fight\Common\Domain\Value\Internet\IpV4Address;
use Fight\Common\Domain\Value\Internet\IpV6Address;

$v4 = IpAddress::fromString('192.0.2.1');     // IpV4Address; '192.0.2.1'
$v6 = IpAddress::fromString('2001:0DB8:0:0:0:0:0:1'); // IpV6Address; '2001:db8::1'
$mapped = IpV6Address::fromString('::ffff:192.0.2.1');
$mapped->toString();                        // '::ffff:c000:201'
$mapped->equals(IpV6Address::fromString('::FFFF:C000:0201')); // true
$mapped->equals($v4);                       // false: mapped IPv6 remains IPv6
// IpV4Address::fromString('::ffff:192.0.2.1') throws DomainException
```

Equality is concrete-family plus canonical address; equal values have equal hashes. String casting, `toString()`,
`hashValue()` and JSON serialization use the canonical string. Generic and family-specific factories reconstruct
that representation; independently constructed equivalent values deduplicate and support membership/removal in
`HashSet`. Values expose no mutable state. No exact PHP serialized-byte or new persistence promise is made.

Lexical validity does **not** imply reachability, trusted provenance, public routing, permission or public-safe
presentation. Valid private, loopback, link-local, multicast, unspecified and broadcast addresses are accepted
as applicable to the family. Construction makes no DNS or network call. Consumers own destination authorization,
address-category restrictions, trusted client-IP sources and SSRF defenses; this is not a CIDR/subnet value or a
sanitization/redaction boundary. Exception prose is not a stable diagnostic contract.

---

## Uri

`Fight\Common\Domain\Value\Internet\Uri`

Full RFC 3986 URI implementation. Parses, validates, normalizes, and resolves URIs. Implements `Comparable`.

### Construction

```php-inline
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

```php-inline
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

```php-inline
$uri->withScheme('http');                    // new instance, scheme changed
$uri->withAuthority(null);                   // remove authority
$uri->withPath('/new/path');                 // replace path
$uri->withQuery(null);                       // remove query
$uri->withFragment(null);                    // remove fragment
```

### Output

```php-inline
$uri->toString();                            // "https://user:pass@api.example.com:8080/path/to?q=1#frag"
$uri->display();                             // "https://api.example.com:8080/path/to?q=1#frag" (no userinfo)
```

### Relative Reference Resolution

```php-inline
$base = Uri::parse('https://example.com/a/b/c');
$uri  = Uri::resolve($base, 'd/e?q=2');
// result: "https://example.com/a/b/d/e?q=2"
```

### Comparison

```php-inline
$uri->compareTo(Uri::parse('https://other.com')); // natural sort of string representation
```

---

## Url

`Fight\Common\Domain\Value\Internet\Url`

Extends `Uri` with HTTP/HTTPS-specific behavior.

### Restricted Scheme

Only `http` and `https` schemes are accepted:

```php-inline
$url = url('https://example.com/path');      // helper
$url = Url::parse('https://example.com/path');
Url::parse('ftp://example.com');             // throws DomainException
```

### Default Port Removal

Standard ports are omitted: port 80 for `http` and port 443 for `https` are stripped.

```php-inline
$url = Url::parse('https://example.com:443/path');
$url->toString();                            // "https://example.com/path"
$url->port();                                // null
```

### Sorted Query Parameters

Query parameters are sorted by key. Parameters without keys (e.g., `=value`) are dropped.

```php-inline
$url = Url::parse('https://example.com/?z=1&a=2');
$url->query();                               // "a=2&z=1"
```

---

## Uuid

`Fight\Common\Domain\Value\Identifier\Uuid`

RFC 4122 UUID implementation with support for versions 1, 3, 4, and 5. Implements `Comparable`.

### Named Constructors

```php-inline
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

```php-inline
$uuid = Uuid::parse('f47ac10b-58cc-4372-a567-0e02b2c3d479');
$uuid = Uuid::fromHex('f47ac10b58cc4372a5670e02b2c3d479');
$uuid = Uuid::fromBytes("\xf4\x7a\xc1\x0b\x58\xcc\x43\x72\xa5\x67\x0e\x02\xb2\xc3\xd4\x79");
$uuid = Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479');
$uuid = Uuid::fromString('urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479');
```

### Validation

```php-inline
Uuid::isValid('not-a-uuid');                 // false
```

### Accessors

```php-inline
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

```php-inline
$uuid->version();                            // 1, 2, 3, 4, or 5 (0 for unknown)
$uuid->variant();                            // VARIANT_RFC_4122 (2) for standard UUIDs
```

### Format Conversion

```php-inline
$uuid->toString();                           // "f47ac10b-58cc-4372-a567-0e02b2c3d479"
$uuid->toUrn();                              // "urn:uuid:f47ac10b-58cc-4372-a567-0e02b2c3d479"
$uuid->toHex();                              // "f47ac10b58cc4372a5670e02b2c3d479"
$uuid->toBytes();                            // 16-byte binary string
$uuid->toArray();                            // associative array of fields
```

### Constants

```php-inline
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

```php-inline
use Fight\Common\Domain\Identity\UniqueId;

final readonly class UserId extends UniqueId {}
```

That is all that is needed. The `UserId` class automatically inherits:

```php-inline
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

```php-inline
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

## StreamId

`Fight\Common\Domain\EventSourcing\StreamId` extends `ValueObject` and implements `Identifier` without UUID
restrictions. Its public constructor retains two nonempty byte strings: stable aggregate name and identifier.
Equality/hash include both components; comparison is bytewise name-first, then identifier, never numeric.
`toString()`, string casts and native JSON use the canonical ASCII `stream:v1:<base64(name)>:<base64(id)>` frame.
`fromString()` strictly reconstructs it with `DomainException` for invalid/noncanonical frames or empty components;
wrong-type comparison also raises `DomainException`, while wrong-type equality is false.

See [Stream identity values](../event-sourcing/index.md#stream-identity-values) for the exact grammar and
[the minor-upgrade notice](../event-sourcing/index.md#streamid-minor-upgrade-notice) for the explicitly approved changes to
hash collection identity and native JSON. Component fields in storage and diagnostics remain unchanged; encoding
is not redaction, authorization or automatic persistence migration.

## Doctrine Data Types

Thirteen custom DBAL types in `Fight\Common\Adapter\Persistence\Doctrine\Type` map domain value objects to SQL
columns, enabling Doctrine ORM to hydrate and dehydrate them directly. Each type extends
`Doctrine\DBAL\Types\Type` and registers under a `common_` prefix.

Most types serialize via `$value->toString()` / `ClassName::fromString()`. The
`MessageDataType` uses `JsonSerializer` instead to support polymorphic message
deserialization through the `Message` interface.

| Type Name | SQL Column | PHP Class | Namespace |
|---|---|---|---|
| `audit_entry_id` | GUID/UUID | `AuditEntryId` | `Domain\Observability` |
| `common_uuid` | GUID/UUID | `Uuid` | `Domain\Value\Identifier` |
| `common_email_address` | VARCHAR | `EmailAddress` | `Domain\Value\Internet` |
| `common_uri` | VARCHAR | `Uri` | `Domain\Value\Internet` |
| `common_url` | VARCHAR | `Url` | `Domain\Value\Internet` |
| `common_string` | VARCHAR | `StringObject` | `Domain\Value\Basic` |
| `common_string_text` | TEXT/CLOB | `StringObject` | `Domain\Value\Basic` |
| `common_mb_string` | VARCHAR | `MbStringObject` | `Domain\Value\Basic` |
| `common_mb_string_text` | TEXT/CLOB | `MbStringObject` | `Domain\Value\Basic` |
| `common_json` | JSON | `JsonObject` | `Domain\Value\Basic` |
| `common_meta` | JSON | `Meta` | `Domain\Messaging` |
| `common_type` | VARCHAR | `Type` | `Domain\Type` |
| `common_message` | JSON | `Message` (interface) | `Domain\Messaging` |

### VARCHAR vs TEXT Variants

`StringObject` and `MbStringObject` each provide two mappings depending on expected field
length. Use `common_string` / `common_mb_string` (VARCHAR) for short strings and
`common_string_text` / `common_mb_string_text` (TEXT/CLOB) for large content.

### Usage in an Entity

```php-inline
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
            audit_entry_id:         Fight\Common\Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType
            common_uuid:            Fight\Common\Adapter\Persistence\Doctrine\Type\UuidDataType
            common_email_address:   Fight\Common\Adapter\Persistence\Doctrine\Type\EmailAddressDataType
            common_uri:             Fight\Common\Adapter\Persistence\Doctrine\Type\UriDataType
            common_url:             Fight\Common\Adapter\Persistence\Doctrine\Type\UrlDataType
            common_string:          Fight\Common\Adapter\Persistence\Doctrine\Type\StringObjectDataType
            common_string_text:     Fight\Common\Adapter\Persistence\Doctrine\Type\StringTextDataType
            common_mb_string:       Fight\Common\Adapter\Persistence\Doctrine\Type\MbStringObjectDataType
            common_mb_string_text:  Fight\Common\Adapter\Persistence\Doctrine\Type\MbStringTextDataType
            common_json:            Fight\Common\Adapter\Persistence\Doctrine\Type\JsonObjectDataType
            common_meta:            Fight\Common\Adapter\Persistence\Doctrine\Type\MetaDataType
            common_type:            Fight\Common\Adapter\Persistence\Doctrine\Type\TypeDataType
            common_message:         Fight\Common\Adapter\Persistence\Doctrine\Type\MessageDataType
```

The former `Fight\Common\Adapter\Doctrine\*DataType` paths remain silent deprecated 1.x
identities for existing consumers; use the canonical paths above for new configuration.
