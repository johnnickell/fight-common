# Utilities

Static utility classes in `Fight\Common\Domain\Utility` that provide common helpers — class name inspection, value hashing, type/format validation, and human-readable variable printing.

---

## Table of Contents

1. [ClassName](#classname)
2. [FastHasher](#fasthasher)
3. [Validate](#validate)
4. [VarPrinter](#varprinter)
5. [Type](#type)

---

## ClassName

`Fight\Common\Domain\Utility\ClassName`

Inspects PHP class names in several formats. Accepts either an object or a string (useful when class names are passed around as configuration strings with dots as namespace separators).

### `ClassName::full(object|string $object): string`

Returns the fully qualified class name. Dot-separated strings are converted to backslash-separated.

```php
ClassName::full(new \App\Dto\User());          // "App\Dto\User"
ClassName::full('App.Dto.User');               // "App\Dto\User"
```

### `ClassName::canonical(object|string $object): string`

Returns the fully qualified class name with backslashes replaced by dots.

```php
ClassName::canonical(new \App\Dto\User());      // "App.Dto.User"
ClassName::canonical('App\Dto\User');           // "App.Dto.User"
```

### `ClassName::underscore(object|string $object): string`

Returns the canonical form, lowercased with underscores inserted before uppercase letters.

```php
ClassName::underscore(new \App\Dto\UserRole()); // "app.dto.user_role"
```

### `ClassName::short(object|string $object): string`

Returns just the class name without the namespace.

```php
ClassName::short(new \App\Dto\User());           // "User"
ClassName::short('App\Dto\User');                // "User"
```

---

## FastHasher

`Fight\Common\Domain\Utility\FastHasher`

Creates a consistent string hash for any PHP value. Useful for caching, deduplication, or identity maps where you need a reliable hash across different value types.

### `FastHasher::hash(mixed $value, string $algorithm = 'fnv1a32'): string`

Each value is prefixed by its type before hashing, preventing collisions between different types:

| PHP Type | Prefix | Example Input |
|---|---|---|
| `object` (Equatable) | `e_` | `$value->hashValue()` |
| `object` (other) | `o_` | `spl_object_hash($value)` |
| `string` | `s_` | the string itself |
| `integer` | `i_` | the integer |
| `double` | `f_` | the float (14-digit precision) |
| `boolean` | `b_` | `0` or `1` |
| `resource` | `r_` | resource ID |
| `array` | `a_` | serialized array |
| other | `0` | literal zero |

```php
FastHasher::hash('hello');                      // fnv1a32 hash of "s_hello"
FastHasher::hash(42);                           // fnv1a32 hash of "i_42"
FastHasher::hash(['a', 'b']);                   // fnv1a32 hash of serialized array
FastHasher::hash($user, 'sha256');              // sha256 hash with same prefixing
```

Objects implementing `Equatable` use the type's own `hashValue()` method; other objects use `spl_object_hash`. The combined string is then hashed with PHP's `hash()` function using the requested algorithm (defaults to `fnv1a32` for speed).

---

## Validate

`Fight\Common\Domain\Utility\Validate`

A broad collection of static type, format, content, and comparison checks. Every method returns `bool`.

### Type Checks

```php
Validate::isScalar(mixed $value): bool
Validate::isBool(mixed $value): bool
Validate::isFloat(mixed $value): bool
Validate::isInt(mixed $value): bool
Validate::isString(mixed $value): bool
Validate::isArray(mixed $value): bool
Validate::isObject(mixed $value): bool
Validate::isCallable(mixed $value): bool
Validate::isNull(mixed $value): bool
Validate::isNotNull(mixed $value): bool
Validate::isTrue(mixed $value): bool
Validate::isFalse(mixed $value): bool
```

Wraps the corresponding PHP functions.

### Empty & Blank

```php
Validate::isEmpty(mixed $value): bool       // PHP's empty()
Validate::isNotEmpty(mixed $value): bool    // !empty()
Validate::isBlank(mixed $value): bool       // trimmed string === ''
Validate::isNotBlank(mixed $value): bool    // trimmed string !== ''
```

`isBlank` and `isNotBlank` first check if the value is string-castable; non-castable values return `false`.

### String Format

| Method | Checks for |
|---|---|
| `isAlpha(mixed $value): bool` | Only alphabetic characters |
| `isAlnum(mixed $value): bool` | Alphanumeric characters |
| `isAlphaDash(mixed $value): bool` | Alphabetic, hyphens, underscores |
| `isAlnumDash(mixed $value): bool` | Alphanumeric, hyphens, underscores |
| `isDigits(mixed $value): bool` | Only digit characters (`ctype_digit`) |
| `isNumeric(mixed $value): bool` | PHP `is_numeric()` |
| `isEmail(mixed $value): bool` | Valid email address |
| `isIpAddress(mixed $value): bool` | Valid IP (v4 or v6) |
| `isIpV4Address(mixed $value): bool` | Valid IPv4 |
| `isIpV6Address(mixed $value): bool` | Valid IPv6 |
| `isUri(mixed $value): bool` | Valid URI per RFC 3986 |
| `isUrn(mixed $value): bool` | Valid URN per RFC 2141 |
| `isUuid(mixed $value): bool` | Valid UUID per RFC 4122 (strips `urn:`, `uuid:`, braces) |
| `isTimezone(mixed $value): bool` | Valid PHP timezone identifier |
| `isJson(mixed $value): bool` | Valid JSON string (including `null`) |

### String Content & Length

```php
Validate::isMatch(mixed $value, string $pattern): bool           // preg_match
Validate::contains(mixed $value, string $search): bool           // str_contains
Validate::startsWith(mixed $value, string $search): bool         // str_starts_with
Validate::endsWith(mixed $value, string $search): bool           // str_ends_with
Validate::exactLength(mixed $value, int $length): bool           // mb_strlen
Validate::minLength(mixed $value, int $minLength): bool
Validate::maxLength(mixed $value, int $maxLength): bool
Validate::rangeLength(mixed $value, int $minLength, int $maxLength): bool
```

Length methods accept an optional `$encoding` parameter (default `UTF-8`) passed to `mb_strlen`.

### Numeric

| Method | Description |
|---|---|
| `exactNumber(mixed $value, int\|float $number): bool` | Loose equality with a number |
| `minNumber(mixed $value, int\|float $minNumber): bool` | Greater than or equal |
| `maxNumber(mixed $value, int\|float $maxNumber): bool` | Less than or equal |
| `rangeNumber(mixed $value, int\|float $min, int\|float $max): bool` | Within inclusive range |
| `wholeNumber(mixed $value): bool` | Integer >= 0 |
| `naturalNumber(mixed $value): bool` | Integer > 0 |
| `intValue(mixed $value): bool` | Value can be cast to int without loss |

### Collections & Arrays

| Method | Description |
|---|---|
| `exactCount(mixed $value, int $count): bool` | `count($value) === $n` |
| `minCount(mixed $value, int $minCount): bool` | `count($value) >= $n` |
| `maxCount(mixed $value, int $maxCount): bool` | `count($value) <= $n` |
| `rangeCount(mixed $value, int $min, int $max): bool` | Count within inclusive range |
| `isOneOf(mixed $value, iterable $choices): bool` | Strict `===` match against choices |
| `keyIsset(mixed $value, mixed $key): bool` | `isset($value[$key])` on array/ArrayAccess |
| `keyNotEmpty(mixed $value, mixed $key): bool` | Key exists and is non-empty |
| `isListOf(mixed $value, ?string $type): bool` | Every element in traversable is of given type |
| `isTraversable(mixed $value): bool` | Array or `Traversable` instance |
| `isCountable(mixed $value): bool` | Array or `Countable` instance |
| `isArrayAccessible(mixed $value): bool` | Array or `ArrayAccess` instance |

### Comparison & Type Introspection

```php
Validate::areEqual(mixed $value1, mixed $value2): bool         // == or Equatable::equals()
Validate::areNotEqual(mixed $value1, mixed $value2): bool
Validate::areSame(mixed $value1, mixed $value2): bool          // ===
Validate::areNotSame(mixed $value1, mixed $value2): bool       // !==
Validate::areSameType(mixed $value1, mixed $value2): bool      // gettype() or ::class
Validate::isType(mixed $value, ?string $type): bool            // instanceof or primitive type
Validate::isComparable(mixed $value): bool                     // instanceof Comparable
Validate::isEquatable(mixed $value): bool                      // instanceof Equatable
Validate::implementsInterface(mixed $value, string $interface): bool
Validate::isInstanceOf(mixed $value, string $className): bool
Validate::isSubclassOf(mixed $value, string $className): bool
```

### Class & Object Existence

```php
Validate::classExists(mixed $value): bool          // class_exists()
Validate::interfaceExists(mixed $value): bool      // interface_exists()
Validate::methodExists(mixed $value, object|string $object): bool
Validate::isStringCastable(mixed $value): bool     // string, null, bool, int, float, Stringable
Validate::isJsonEncodable(mixed $value): bool      // json_encode produces a string
```

### Filesystem

```php
Validate::isPath(mixed $value): bool       // file_exists()
Validate::isFile(mixed $value): bool       // is_file()
Validate::isDir(mixed $value): bool        // is_dir()
Validate::isReadable(mixed $value): bool   // is_readable()
Validate::isWritable(mixed $value): bool   // is_writable()
```

---

## VarPrinter

`Fight\Common\Domain\Utility\VarPrinter`

Converts any PHP value into a human-readable string representation. Useful for logging, error messages, and debug output where you need a consistent, concise view of a variable.

### `VarPrinter::toString(mixed $value): string`

The output depends on the type and value:

| Input | Output |
|---|---|
| `null` | `NULL` |
| `true` | `TRUE` |
| `false` | `FALSE` |
| `UnitEnum` | `Enum(EnumName::CaseName)` |
| `resource` | `Resource(id:type)` |
| `Closure` | `Function` |
| `DateTimeInterface` | `DateTime(2026-05-08T12:00:00+00:00)` |
| `Throwable` | `RuntimeException({"message":"...","code":0,"file":"...","line":42})` |
| Object with `toString()` | Result of `toString()` |
| `Stringable` | `(string) $value` |
| Other object | `Object(Fully\Qualified\ClassName)` |
| Array | `Array(0 => a, 1 => b)` (recursive) |
| `NAN` | `NAN` |
| `INF` | `INF` |
| `-INF` | `-INF` |
| Other scalar | `(string) $value` |

```php
VarPrinter::toString(null);                   // "NULL"
VarPrinter::toString(true);                   // "TRUE"
VarPrinter::toString([1, 2, 3]);              // "Array(0 => 1, 1 => 2, 2 => 3)"
VarPrinter::toString(new \DateTime());        // "DateTime(2026-05-08T12:00:00+00:00)"

try {
    throw new \RuntimeException('Oops');
} catch (\Throwable $e) {
    VarPrinter::toString($e);                 // 'RuntimeException({"message":"Oops","code":0,"file":"...","line":...})'
}

enum Status: string { case Active = 'active'; }
VarPrinter::toString(Status::Active);         // "Enum(Status::Active)"
```

---

## Type

`Fight\Common\Domain\Type\Type`

Wraps a class name as a value object, using canonical (dot-separated) format internally. Implements `Equatable`, `JsonSerializable`, and `Stringable`, making it safe for serialization, caching, and identity maps.

### `Type::create(object|string $object): Type`

Creates an instance from an object or class name string.

### `Type::toClassName(): string`

Returns the fully qualified class name (backslash-separated).

### `Type::toString(): string`

Returns the canonical class name (dot-separated).

```php
$type = Type::create(new \App\Dto\User());
$type = Type::create('App.Dto.User');

$type->toClassName();                        // "App\Dto\User"
$type->toString();                           // "App.Dto.User"
(string) $type;                              // "App.Dto.User"
json_encode($type);                          // '"App.Dto.User"'

// Equality
Type::create('App.Dto.User')->equals(Type::create('App.Dto.User')); // true
```
