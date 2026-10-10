Value objects are immutable, self-validating domain primitives. They measure, quantify, or describe something in the domain — they are not entities with identity, but rather values that are compared by their content rather than by reference.

All value objects in this library extend `ValueObject`, which implements the `Value` interface (`Equatable` + `JsonSerializable` + `Stringable`). Default equality requires the same concrete type and `toString()` value; default hashing uses only `toString()`, so unequal types may share a hash. `InstantRange` overrides both to use exact endpoint instants even when its serialized timezone context differs.
`Money` overrides both to use code, captured scale and minor units rather than saved definition-version metadata.

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

Sections show helpers where available and named factories; the IP, phone, calendar/local-time and Duration values have no helpers.
WeekDay is a native enum, not a ValueObject.

---

## Table of Contents

1. [StringObject](#stringobject)
2. [MbStringObject](#mbstringobject)
3. [JsonObject](#jsonobject)
4. [StrictJson](#strictjson)
5. [EmailAddress](#emailaddress)
6. [IP addresses](#ip-addresses)
7. [E164PhoneNumber](#e164phonenumber)
8. [Decimal](#decimal)
9. [Currency](#currency)
10. [Money](#money)
11. [Uri](#uri)
12. [Url](#url)
13. [Uuid](#uuid)
14. [Identity (UniqueId)](#identity-uniqueid)
15. [StreamId](#streamid)
16. [Calendar and local time](#calendar-and-local-time)
17. [Inclusive calendar DateRange](#inclusive-calendar-daterange)
18. [Strict zoned DateTime](#strict-zoned-datetime)
19. [Half-open InstantRange](#half-open-instantrange)
20. [Duration](#duration)
21. [Doctrine Data Types](#doctrine-data-types)

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

## E164PhoneNumber

`Fight\Common\Domain\Value\Internet\E164PhoneNumber`

A final readonly lexical phone value, constructed with `fromString()`; no helper, Identifier, ordering,
extension point or Doctrine mapping is added.

### E.164 lexical form and value identity

**Contract ID:** `fight-common.behavior.e164-phone-number`

Accept exactly `\A\+[1-9][0-9]{0,14}\z`: a leading plus, a nonzero first ASCII digit and one through fifteen
ASCII digits total, excluding the plus. There is no arbitrary minimum or country-code/assignment database.
`+1` and `+9` are valid lexical values, not claims of assigned or dialable numbers. Keep phone data as strings,
never numeric casts. Construction does not trim whitespace, remove separators or convert national prefixes.

```php-inline
use Fight\Common\Domain\Value\Internet\E164PhoneNumber;

$number = E164PhoneNumber::fromString('+15550001234');
$number->toString();                          // '+15550001234'
(string) $number;                            // '+15550001234'
json_encode($number);                        // '"+15550001234"'
$number->equals(E164PhoneNumber::fromString($number->toString())); // true
// E164PhoneNumber::fromString('+0987654321') throws DomainException
// E164PhoneNumber::fromString('+1234567890123456') throws DomainException
```

Empty/plus-only input, leading zero, missing/double/embedded plus, letters, Unicode digits/lookalikes, all
whitespace/control bytes, separators, extensions and overlength strings raise `DomainException` without repair.
Unprefixed short codes and alphanumeric sender IDs are not values of this type. Native PHP parameter typing
remains separate from invalid-string validation; exception prose is not a stable diagnostic contract.

The accepted text is retained exactly by `toString()`, string casting, `hashValue()` and JSON serialization.
Equality is same-concrete-type plus exact string, and equal values have equal hashes. Reconstruct via `fromString()`
from the emitted string or decoded JSON string. Values expose no mutable state. No exact PHP serialized-byte or
new persistence contract is introduced.

Adoption is optional: explicitly pass `toString()` into the existing [SMS string APIs](../sms/index.md#optional-lexical-phone-adoption).
Existing short codes, sender IDs and non-E.164 strings remain supported there; neither value construction nor
message creation sends anything. Lexical validity does not establish assignment, reachability, SMS capability,
provider eligibility, ownership or permission to contact. Consumers own sender selection, country/provider rules,
consent, authorization, redaction and delivery policy. Construction performs no network/provider lookup.

---

## Decimal

Contract ID: `fight-common.behavior.exact-decimal-arithmetic`.

`Fight\Common\Domain\Value\Basic\Decimal` is an opt-in final readonly exact base-ten ValueObject and
Comparable. `fromString(string)` accepts an optional sign, mandatory ASCII integer digits and an optional dot
followed by one or more ASCII fractional digits. It does not trim, accept exponents/localized notation or convert
floats. Input is limited to 4,096 bytes; the **normalized** coefficient has at most 1,024 digits and its normalized
scale is 0–1,024. Excess leading/trailing zeros may fit in the input even when the unnormalized coefficient is
longer. Invalid inputs, unsupported exact results, negative/requested scales above 1,024, zero divisors and
nonterminating exact quotients raise `DomainException`. Native PHP argument and enum type errors remain native.

```php-inline
use Fight\Common\Domain\Value\Basic\Decimal;

$sum = Decimal::fromString('0.1')->add(Decimal::fromString('0.2')); // 0.3 exactly
$sum->toString(); // '0.3'
Decimal::fromString('+01.000')->equals(Decimal::fromString('1')); // true
Decimal::fromString('1')->divide(Decimal::fromString('8'))->toString(); // '0.125'
Decimal::fromString('1')->divideRounded(Decimal::fromString('3'), 2, RoundingMode::HalfEven)->toString(); // '0.33'
Decimal::fromString('-2.5')->round(0, RoundingMode::HalfEven)->toString(); // '-2'
```

`add`, `subtract`, `multiply` and `divide` return exact supported values or reject; no implicit rounding.
`divideRounded(Decimal $divisor, int $scale, RoundingMode $mode)` and
`round(int $scale, RoundingMode $mode)` require an explicit precision and one of **all eight** native PHP rounding
modes. The requested scale controls rounding, not stored zero padding. Identity, hash, ordering (-1/0/1), string
cast and JSON string use the normalized numeric value: `-0`, `0.00` and `0` are one value. `fromString()` reconstructs
string or decoded JSON; wrong-type equality is false and wrong-type comparison raises `DomainException`.

The implementation uses plain PHP exact digits without requiring BCMath, GMP or a third-party arithmetic backend;
it does not change global `bcscale`, locale or float precision. Passing a string made from an already-rounded native
float cannot recover precision lost by the caller. The new factories, operations, representations and documented
throwable families are additive public contracts under ADRs 0009–0011; no pre-existing consumer value changes,
automatic persistence mapping, subclassing contract, PHP serialized-byte format, unlimited precision or currency
policy is supplied. Consumers own financial rounding/admission rules and optional adoption.

---

## Currency

Contract ID: `fight-common.behavior.bounded-currency-definitions`.

`Fight\Common\Domain\Value\Money\Currency` is a final readonly value. `fromCode()` and `fromString()`
accept **only** the uppercase ASCII codes below without trimming, normalization or lookup; malformed or unsupported
codes (including BGN) raise `DomainException`, not a guessed exponent. This is a deliberately bounded package-owned
set, not a complete global/historical catalog, ranking, legal-tender declaration or authorization to transact.

| Base-ten accounting exponent | Supported codes |
|---|---|
| 0 | CLP, JPY, KRW, VND |
| 3 | BHD, KWD |
| 2 | AED, ARS, AUD, BDT, BRL, CAD, CHF, CNY, COP, CZK, DKK, EGP, EUR, GBP, HKD, HUF, IDR, ILS, INR, KES, LKR, MAD, MXN, MYR, NGN, NOK, NZD, PEN, PHP, PKR, PLN, QAR, RON, RUB, SAR, SEK, SGD, THB, TRY, TWD, UAH, USD, ZAR |

```php-inline
use Fight\Common\Domain\Value\Money\Currency;

$currency = Currency::fromCode('JPY');
$currency->code();               // 'JPY'
$currency->accountingExponent(); // 0
$currency->definitionVersion();  // 'v1'
$currency->toString();           // 'JPY'
$old = Currency::fromDefinition('JPY', 'v1'); // explicit retained reader
```

All 49 initial codes have definition version `v1`. `fromDefinition(code, version)` retrieves **only** a retained
package definition and rejects unknown codes/versions. A saved Money reader must retain its code, definition version,
scale and exact minor units; it must never reconstruct via the *current* `fromCode()` definition alone. Currency's
string cast, native JSON string, hash and same-concrete-type equality use **code only**; `fromString()`/decoded JSON
reconstruct the current definition for that code. A future different version with the same code remains Currency-equal.
Money compares captured code and scale separately: metadata-only revisions at the same exponent remain compatible;
a changed exponent does not. Currency does not itself store or reconstruct Money. It carries immutable scalar
metadata and exposes no mutable shared data.

**Maintenance:** the package's internal `CurrencyDefinitions` snapshot owns code → version → exponent and code →
current version. New codes require deliberate documented additions and tests. A changed scale needs a new version,
retention of all old version/exponent pairs for backward reads and an explicit update to current selection;
`revised()` rejects dropping or changing a prior definition. Existing supported definitions are pinned by the
49-code test data. Do not alter an old entry or erase an old code, silently redenominate saved values, register
consumer-invented scales, or infer precision from external tables, the clock or online data. No numeric codes,
display names, exchange rates, physical denominations, cash rounding, persistence mapping or automatic conversion
are provided. Consumers decide transaction eligibility, accounting/regulatory and cash policy. Adoption is optional;
existing values and consumers are unchanged. Public factory/accessor/error/representation behavior is additive under
ADRs 0009–0011; no consumer subclassing, new platform/dependency floor, exact PHP serialized bytes or global currency
coverage is promised. Exception prose is diagnostic rather than public-safe.

---

## Money

Contract ID: `fight-common.behavior.exact-money-and-allocation`.

`Fight\Common\Domain\Value\Money\Money` is a final readonly ValueObject and Comparable. It captures a
supported `Currency` definition at `fromMinorUnits(int $minorUnits, Currency $currency)` or
`fromDecimal(Decimal $majorAmount, Currency $currency, ?RoundingMode $mode = null)`. Its amount is **signed native
integer minor units**, including zero, negatives and `PHP_INT_MIN`; `minorUnits()` returns that exact integer,
`currency()` returns the captured immutable context, and `toDecimal()` returns the exact major-unit Decimal.
The accounting exponent is 0, 2 or 3, not a cash-rounding or physical subdivision rule. Construction and
reconstruction do not consult an online table. Unsupported codes, definitions and caller-invented scales are
not supported. There is no mandatory 64-bit, BCMath, GMP or float backend.

```php-inline
use Fight\Common\Domain\Value\Basic\Decimal;
use Fight\Common\Domain\Value\Money\Currency;
use Fight\Common\Domain\Value\Money\Money;

$usd = Currency::fromCode('USD');
$price = Money::fromDecimal(Decimal::fromString('1.23'), $usd); // 123 minor units
$price->add(Money::fromMinorUnits(2, $usd))->toDecimal()->toString(); // '1.25'
Money::fromDecimal(Decimal::fromString('-1.005'), $usd, RoundingMode::HalfAwayFromZero)
    ->minorUnits(); // -101
$shares = Money::fromMinorUnits(-5, $usd)->allocate([1, 1, 1]);
array_map(static fn (Money $share): int => $share->minorUnits(), $shares); // [-2, -2, -1]
```

`add`, `subtract`, `negate`, `multiply(Decimal $scalar, ?RoundingMode $mode = null)`, and
`divide(Decimal $scalar, ?RoundingMode $mode = null)` return new Money. Exact paths reject a fractional minor
unit, even when a decimal result is otherwise finite; `divide` without rounding also rejects a nonterminating
Decimal quotient. With a mode, scalar division rounds the exact ratio **once at minor-unit scale zero**, not at
an intermediate major scale. Decimal conversion scales exactly first, then makes one explicit rounding decision
at the minor-unit boundary. All eight native PHP `RoundingMode` cases are supported. Native integer limits are
checked **after** exact wide arithmetic, without `abs(PHP_INT_MIN)`, native float multiplication or implicit
precision loss. A zero divisor and unrepresentable result reject. Amount comparison and addition/subtraction
require matching currency code **and captured scale**, not matching revision labels; different currencies or
scales reject rather than sorting, exchanging or redenominating. `compareTo` returns -1/0/1 for compatible Money.

`allocate(array $weights)` accepts only a nonempty **list** of nonnegative native integer weights with at least
one positive weight (even when allocating zero). It divides the exact absolute amount by the exact total weight,
takes floor shares, gives remaining minor units to the greatest fractional remainders (ties to the first original
index), restores the original sign and returns Money shares in original list order. Zero weights get zero; the
signed shares sum exactly to the original amount, even at native limits. Large sums/products are calculated exactly.
This includes equal-weight splitting, not a policy about fairness or authority to distribute funds.

Saved text and JSON are the same **string**, `money:v1:CODE:definitionVersion:scale:signedMinorUnits` (for example
`money:v1:USD:v1:2:-123`). `fromString()` reads only canonical text with an exact ASCII signed integer (no `+`,
leading zeros or negative zero), the saved scale and a **retained** package definition. It rejects unknown schema/
definition/code/scale, mismatches, malformed values and amounts outside the native integer range, never inferring
scale from today's current code definition. Exact minor units remain text in JSON, so a JSON decoder must retain
the string rather than parse an imprecise numeric JSON amount. Readers for previously supported definitions
remain available when new codes or definition versions are added; changed exponents require a *new* retained
version, not mutation of an old entry. Equality/hash and hash-collection membership use code, captured scale and
minor units, not definition version. Saved representations also carry provenance, so two numerically equal Money
values with different supported definition versions may have **different strings/JSON**. Reconstruct with
`fromString()` or from the decoded JSON string, not from `Currency::fromCode()` alone. PHP argument/type failures
remain native; invalid supported values, incompatibility, overflow and malformed saved data raise
`DomainException` (diagnostic messages are not public-safe contracts). Caller-side float or JSON numeric precision
loss cannot be recovered. Money's public factories accept only retained package definitions; hypothetical future
snapshots in tests are not consumer construction or reader APIs.

This new opt-in public factory/reader, operations, failure family and versioned representation are additive under
ADRs 0009–0011; no pre-existing Money schema or persisted value is migrated. No extension contract, PHP serialized
bytes, database mapping or automatic adoption is supplied. Consumers own currency eligibility, accounting/legal and
cash rules, refunds/debts, permissions, ledger/payment writes and any persistence integration. Recognition is not
permission to transact.

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

## Calendar and local time

`Date`, `Time` and `WeekDay` live under `Fight\Common\Domain\Value\DateTime`. They are optional new concepts, not
replacements for native timestamps or existing `Timezone`. Neither a date nor a local time identifies an instant,
contains a zone or grants scheduling/record-access permission. Consumers own calendar, locale and scheduling policy.
No ambient clock, midnight/default-date conversion, persistence mapping, arithmetic or automatic adoption is supplied.
The code is original; these APIs do not adopt Novuso's constructors or sequence semantics.

### Gregorian Date values

Contract ID: `fight-common.behavior.calendar-date-values`.

Final readonly `Date` extends `ValueObject` and implements `Comparable`. Its factories are
`fromParts(int $year, int $month, int $day)`, `fromString(string $value)` and `fromNative(DateTimeInterface $value)`.
Supported dates are proleptic Gregorian years **0001–9999**, including before historical Gregorian adoption.
Leap years are divisible by four, except centuries not divisible by 400. Year zero, invalid month/day, non-leap
February 29 and rollover reject with `DomainException`; native parameter typing remains distinct from validation.

The only accepted string spelling is exact ASCII `YYYY-MM-DD`: no trimming, short fields, signs, trailing newlines
or native free-form parser. `toString()`, string casts, JSON and `hashValue()` use that canonical date.
`year()`, `month()`, `day()` return integer components; `weekDay()` returns the Gregorian `WeekDay` case using
PHP's Sunday-zero `w` convention. It formats an already-validated date with explicit UTC internally, so ambient
zones (including skipped local dates) cannot change the weekday. This does not assign a consumer timezone/instant
to the value or introduce a public date-to-timestamp conversion.
Equality is same-concrete-type canonical equality; wrong-type equality is false. `compareTo()` returns -1/0/1
in chronological order, coherent with equality, and raises `DomainException` for a non-Date.

```php-inline
use Fight\Common\Domain\Value\DateTime\Date;
use Fight\Common\Domain\Value\DateTime\WeekDay;

$date = Date::fromParts(2024, 2, 29);
$date->toString();                               // "2024-02-29"
$date->weekDay() === WeekDay::THURSDAY;            // true
$date->compareTo(Date::fromString('2024-03-01'));  // -1
Date::fromString('2023-02-29');                   // throws DomainException, no March rollover
```

`fromNative()` copies the supplied timestamp's **own local** year/month/day without ambient-zone conversion or
retaining the native object; unsupported extracted years reject. Mutating the caller's DateTime afterward cannot
change the Date. It extracts existing components, not the text originally parsed by the native timestamp: native
normalization that already occurred is not undone. It does not recombine a date and time into a zoned timestamp.

### Exact local Time values

Contract ID: `fight-common.behavior.local-time-values`.

Final readonly `Time` extends `ValueObject` and implements `Comparable`. Factories are
`fromParts(int $hour, int $minute, int $second, int $microsecond = 0)`, `fromString(string $value)` and
`fromNative(DateTimeInterface $value)`. Bounds are hour 0–23, minute/second 0–59 and microsecond 0–999999.
Only the microsecond component defaults to zero. Invalid components, **24:00**, leap seconds and fractional overflow
raise `DomainException`, never rollover. Native parameter typing is not replaced with coercive domain parsing.

Only exact ASCII `HH:MM:SS.ffffff` strings are accepted: exactly six fractional digits, no timezone suffix,
short fields, missing fraction, whitespace or parser normalization. String/JSON/hash outputs retain exactly six
digits. Microseconds are integers, never floating-point time. Accessors are `hour()`, `minute()`, `second()` and
`microsecond()`. Equality/hash use the same-concrete-type canonical value; wrong-type equality is false.
`compareTo()` returns -1/0/1 in local component order, coherent with equality; non-Time input raises DomainException.

```php-inline
use Fight\Common\Domain\Value\DateTime\Time;

$time = Time::fromParts(1, 2, 3, 4);
$time->toString();                              // "01:02:03.000004"
$time->microsecond();                           // 4
Time::fromParts(1, 2, 3)->toString();            // "01:02:03.000000"
Time::fromString('23:59:59.999999');             // maximum local time
```

`fromNative()` copies local hour/minute/second/microsecond from the supplied DateTimeInterface, with no ambient-zone
conversion, date interpretation, instant arithmetic or retained mutable input. Native pre-epoch timestamps still
contribute their exact local microseconds. Mutating a native input cannot change the resulting Time. Stable string
and JSON reconstruction uses the factories above; PHP serialized bytes and automatic persistence are not promised.

### Native WeekDay

Contract ID: `fight-common.behavior.weekday-values`.

`WeekDay` is a native integer-backed enum matching PHP's `DateTimeInterface::format('w')`: `SUNDAY=0`, `MONDAY=1`,
`TUESDAY=2`, `WEDNESDAY=3`, `THURSDAY=4`, `FRIDAY=5`, `SATURDAY=6`. Use case identity, `name`, `value` and native
`cases()`, `from()` or `tryFrom()`; it is not a ValueObject or Identifier and adds no value-object string/hash/comparison
factories. Native JSON encodes the integer
backing value. Invalid integer `from()` raises PHP `ValueError`, `tryFrom()` returns null; PHP's native parameter
rules still apply (for example a string backing value raises TypeError in strict calling code). There is no
DomainException wrapper, ISO Sunday-seven alias, locale-dependent renumbering or consumer extension promise.

```php-inline
WeekDay::from(0) === WeekDay::SUNDAY;            // true
WeekDay::from(1) === WeekDay::MONDAY;            // true
WeekDay::tryFrom(7);                            // null
json_encode(WeekDay::SUNDAY);                   // "0" (JSON number)
```

## Inclusive calendar DateRange

Contract ID: `fight-common.behavior.inclusive-calendar-date-range`.

`Fight\Common\Domain\Value\DateTime\DateRange` is a final readonly `ValueObject` representing an inclusive
calendar interval `[start, end]`. Construct with `fromDates(Date $start, Date $end)`; `start()` and `end()` return
immutable Date values. `contains(Date $candidate)` includes both endpoints and every intervening date, irrespective
of weekday, month, leap day or year. Equal endpoints represent one date, **not** an empty range. Reversed dates raise
`DomainException`; Dates themselves enforce proleptic Gregorian years **0001–9999**. No timestamp, timezone,
midnight or step alignment is involved.

```php-inline
use Fight\Common\Domain\Value\DateTime\Date;
use Fight\Common\Domain\Value\DateTime\DateRange;

$range = DateRange::fromDates(Date::fromString('2024-02-28'), Date::fromString('2024-03-01'));
$range->contains(Date::fromString('2024-02-29')); // true
$range->end()->toString(); // "2024-03-01"
$copy = DateRange::fromString($range->toString());
```

The exact ASCII string/JSON/hash representation is `date-range:v1:YYYY-MM-DD:YYYY-MM-DD`, start first. `fromString()`
accepts only that frame with two valid canonical Dates in order; unsupported versions, malformed dates and reversed
intervals raise `DomainException`. `equals()` requires the same concrete type and equal ordered endpoints; wrong-type
equality is false, and equal ranges have equal hashes. There is no ordering contract for ranges, PHP serialized-byte
promise, persisted schema or new dependency. Exception prose is diagnostic, not public-safe.

This is original additive code, not Novuso's stepped DateRange. Its public callable/constructible operations and
behavioral/representation contract are classified under ADRs 0009–0011; it is final, not an extension or
implementer contract. Adoption is optional; existing native timestamps, inclusive audit lookups, scheduler and
consumer data are unchanged. Calendar containment is not proof of occurrence or permission to act. Consumers own
calendar, retention and access policy. Half-open instant intervals are a separate concept; there is no range
length/count, iteration, recurrence, date arithmetic or automatic conversion to instants. Direct nonvisual
containment and reconstruction tests are the appropriate acceptance evidence.

## Strict zoned DateTime

Contract ID: `fight-common.behavior.strict-zoned-datetime`.

`Fight\Common\Domain\Value\DateTime\DateTime` is a final readonly value with an explicit timezone and an exact instant. It is separate from native `DateTimeImmutable`, `Date` (calendar-only), `Time` (local-only), `Timezone` (whose original string identity is unchanged) and elapsed `Duration`. Its code is original, not adapted from Novuso. No existing native timestamp signatures, audit range semantics, persistence formats or scheduler policy change; opt in explicitly.

```php-inline
use Fight\Common\Domain\Value\DateTime\{Date, DateTime, Time, Timezone};

$zone = Timezone::fromString('America/New_York');
$fold = DateTime::fromLocal(Date::fromString('2026-11-01'), Time::fromString('01:30:00.123456'), $zone, -18000);
$fold->toNative()->format('U.u'); // 1793514600.123456
$copy = DateTime::fromString($fold->toString()); // exact second fold, not a fresh wall-time choice
$utc = $fold->inTimezone(Timezone::fromString('UTC')); // same instant, different local components
$other = $fold->reinterpretInTimezone(Timezone::fromString('UTC')); // same wall components, different instant
```

`fromLocal(Date, Time, Timezone, ?int $offsetSeconds = null)` rejects nonexistent local times even with an offset. Repeated times require an exact signed integer offset in **seconds** selecting a real occurrence; a supplied offset for a unique time must also match. No one-hour/whole-minute assumption is made: historical second offsets, non-hour transitions, fixed-offset and abbreviation zones are supported. Native timezone rules are evaluated at construction, not pinned to a tzdb version. `reinterpretInTimezone()` repeats these checks on the target wall components; `inTimezone()` preserves the instant but rejects a target local year outside 0001–9999. Neither operation performs date or Duration arithmetic.

`fromNative(DateTimeInterface)` copies the supplied native instant/timezone (including a mutable `DateTime`) without retaining mutable caller state. `fromInstant(string $seconds, int $microsecond, Timezone $timezone)` takes canonical signed Unix seconds (`0` or `-?[1-9][0-9]*`) and a 0–999999 microsecond component, without floating point or a combined integer-microsecond total. It validates the resulting local date. `toNative()` returns an immutable timestamp; `date()`, `time()`, `timezone()` and `instantSeconds()` expose values/coordinates. Invalid text, offsets, out-of-range local years and unrepresentable coordinates raise `DomainException`; native PHP argument type failures remain native. Unexpected native engine errors are not converted to domain validation failures. Exception messages/causes are diagnostic, not public-safe. The supported range is the 0001–9999 **local** year in the chosen zone; crossing it during conversion rejects rather than clamps.

String/JSON/hash use `zoned:v1:<canonical signed Unix seconds>:<six microsecond digits>:<canonical Base64 native timezone identifier>`; `fromString()` strictly reconstructs that instant and native identifier, including either overlap occurrence. Names are `DateTimeZone::getName()` values: IANA aliases remain distinct, while native offset normalization makes `+5:30` and `+05:30` the same identifier (`+05:30`). Pre-epoch `-1.500000` means whole second `-1` and microseconds `500000`, **not** negative one-and-a-half seconds. Saved instants stay instants; timezone rule updates may change displayed local components but never silently reinterpret the stored wall time. No exact PHP serialized-byte, frozen tzdb or new Doctrine mapping is promised.

`equals()`/hash require both exact instant and native timezone identifier; `compareTo()` orders instants first and identifier second (-1/0/1), rejecting another type with DomainException. `compareInstantTo(DateTime)` and `isSameInstantAs(DateTime)` deliberately ignore the identifier so instant intervals need not inherit value tie-breaking. Wrong-type equality is false. All factories are additive callable/constructible-only operations under ADRs 0009–0011, without a new consumer-implementable/extension contract, platform/dependency floor or automatic migration. Consumers own occurrence provenance, scheduling/access eligibility and any presentation/redaction policy. Nonvisual native/DST and test transcripts are the appropriate acceptance evidence.

## Half-open InstantRange

Contract ID: `fight-common.behavior.half-open-instant-range`.

`Fight\Common\Domain\Value\DateTime\InstantRange` is a final readonly value for exact instants in
`[start, end)`. Construct with `fromInstants(DateTime $start, DateTime $end)`; `start()` and `end()` return the
original immutable zoned endpoint values. `contains(DateTime $candidate)` includes the starting instant and excludes
the ending instant, at microsecond precision, regardless of any of their timezone identifiers. Reversed instants
raise `DomainException`; equal instants (even if their zones differ) form an empty interval containing nothing.
The endpoints must be supported strict zoned DateTimes (local year 0001–9999 in each endpoint zone). No separate
span limit or Duration calculation is imposed.

```php-inline
use Fight\Common\Domain\Value\DateTime\{DateTime, InstantRange, Timezone};

$utc = Timezone::fromString('UTC');
$start = DateTime::fromInstant('-1', 500000, $utc);
$end = DateTime::fromInstant('0', 1, $utc);
$range = InstantRange::fromInstants($start, $end);
$range->contains($start->inTimezone(Timezone::fromString('America/New_York'))); // true
$range->contains($end); // false
$copy = InstantRange::fromString($range->toString()); // exact instants and endpoint zone context
```

String casts and JSON strings use the versioned ASCII frame
`instant-range:v1:<canonical Base64 zoned DateTime string>:<canonical Base64 zoned DateTime string>`.
Each nested `zoned:v1` representation preserves signed Unix seconds, six-digit microseconds and the original native
timezone identifier. Base64 frames avoid ambiguous separators. `fromString()` requires canonical Base64 and valid
zoned endpoints in instant order; malformed, unsupported, out-of-range or reversed frames raise `DomainException`.
Both factory and reconstructed getters retain their supplied endpoint zones, even at instants whose UTC local year
falls outside 0001–9999. A range with the same exact endpoint instants in different zones has a **different string
and JSON representation**, because they retain context, but compares equal and has the same hash. `InstantRange`
overrides the normal ValueObject string-based equality/hash: its hash is the canonical coordinate frame
`instant-range:v1:<seconds>:<six digits>:<seconds>:<six digits>` without timezone labels. For the example above it
is `instant-range:v1:-1:500000:0:000001`. Distinct endpoint pairs, including empty ranges at different instants,
remain distinct. Do not use string/JSON byte identity as a test of range equality. Exception prose is diagnostic,
not public-safe; native PHP argument typing remains separate.

This original additive callable/constructible API and versioned behavior are classified under ADRs 0009–0011. It
adds no consumer extension or implementer contract, dependency, persistence mapping or existing-data migration.
Adoption is optional: neither native timestamp APIs nor `AuditRepository::getBetween(DateTimeImmutable,
DateTimeImmutable, Pagination)` change. That audit query remains **inclusive on both endpoints**, using `>=` and
`<=` in the Doctrine adapter; do not pass an InstantRange as if it changed audit lookup semantics. This value is
not proof of occurrence, authorization or record access. Consumers own access, schedules and optional conversions;
there is no range length, iteration, recurrence, calendar interval or automatic audit/scheduler migration.
Direct nonvisual containment/reconstruction and controlled audit translation tests establish the package boundary,
not database execution.

## Duration

`Fight\Common\Domain\Value\DateTime\Duration` represents signed **fixed elapsed time**, not a date, local time,
instant, timezone, calendar month/year or DST-aware wall-clock operation. This is original, additive code, not a
Novuso API adaptation. Adoption is optional; existing native timestamps, Timezone, scheduler, timeout and TTL
contracts remain unchanged. There is no helper, persistence mapping, DateTime arithmetic or interval-length API.

### Exact elapsed Duration values

Contract ID: `fight-common.behavior.exact-elapsed-durations`.

Final readonly `Duration` extends `ValueObject` and implements `Comparable`. It stores an exact integer total of
microseconds in the **native PHP_INT_MIN through PHP_INT_MAX** range, including zero and negative values. No 64-bit
floor, float approximation, required arithmetic extension or arbitrary-precision storage is introduced. Numeric
range therefore depends on the runtime: a value representable on one integer width may reject on a narrower one.

Factories are `fromMicroseconds(int $microseconds)`, `fromMilliseconds(int $milliseconds)`,
`fromSeconds(int $seconds)` and `fromString(string $value)`. Integer-unit factories scale exactly, checking bounds
**before multiplication**. `toMicroseconds()` returns the stored total; `toMilliseconds()` and `toSeconds()` return
an exact integer quotient or raise `DomainException` for any fractional unit, including negative fractions.
There is no implicit rounding/truncation or floating result. Native PHP parameter typing remains distinct from
value validation; strict calling code rejects floats with TypeError. Use exact integers, not weak-call coercion.

```php-inline
use Fight\Common\Domain\Value\DateTime\Duration;

$elapsed = Duration::fromSeconds(2);
$elapsed->toMilliseconds();                       // 2000
$next = $elapsed->add(Duration::fromMicroseconds(1));
$next->toString();                               // "2000001us"
$elapsed->toString();                            // "2000000us" (unchanged)
$next->toSeconds();                              // throws DomainException: fractional second
Duration::fromMilliseconds(-2)->toMicroseconds(); // -2000
Duration::fromMicroseconds(-1)->negate()->toString(); // "1us"
```

Canonical string/JSON/hash representation is ASCII decimal microseconds followed by **`us`**, with grammar
`\A(0|-?[1-9][0-9]*)us\z` and native numeric bounds. Zero is `0us`; no plus, leading zeros, negative zero, whitespace,
Unicode digits, separators, exponent, fractional spelling or other units/ISO calendar-duration syntax is accepted.
`fromString()` checks decimal magnitude before casting; out-of-range text never clamps or becomes a float.
`toString()`, string casting and JSON emit the same exact text, independently of ambient float precision settings.
Reconstruct from that string or the decoded JSON string; no PHP serialized-byte or automatic persistence promise
is added.

`add(Duration $other)`, `subtract(Duration $other)` and `negate()` return new values without changing either
operand. All check representability before evaluating arithmetic. Subtraction does not first negate its operand:
subtracting PHP_INT_MIN from itself succeeds as zero. PHP_INT_MIN itself is valid, but its negation rejects because
the positive magnitude is unrepresentable. Malformed/out-of-range text, scaled overflow, fractional exact
conversion and arithmetic overflow raise `DomainException`; exception prose is not a stable contract.

Equality requires the same concrete type and exact canonical total; equal durations have equal hashes and
deduplicate in value collections. Wrong-type equality is false. `compareTo(mixed $other)` returns **-1/0/1 numeric
ordering**, coherent with equality (not lexicographic string ordering), and raises DomainException for non-Duration
values. Values expose no mutable state and have no consumer-extension or Identifier promise.

Signed duration validity does not establish occurrence, permission, a timeout deadline or suitability for a TTL.
Consumers own positivity, limits, scheduling/calendar policy and optional explicit conversion when adopting the
value in their own APIs. Construction and arithmetic perform no clock, network, persistence or scheduler effects.

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
