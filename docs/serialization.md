# Serializers

Two cooperating layers: the `Serializable` interface lets domain objects opt in to being serialized, and `Serializer` implementations convert those objects to and from string formats.

```
Serializable (interface)
  │  arraySerialize(): array
  │  arrayDeserialize(array $data): static
  │
  └── YourDomainObject implements Serializable

Serializer (interface)
  │  serialize(Serializable $object): string
  │  deserialize(string $state): Serializable
  │
  ├── JsonSerializer   → JSON strings
  └── PhpSerializer    → PHP-serialized strings
```

---

## Table of Contents

1. [The Serializable Interface](#the-serializable-interface)
2. [The Serializer Interface](#the-serializer-interface)
3. [Envelope Format](#envelope-format)
4. [JsonSerializer](#jsonserializer)
5. [PhpSerializer](#phpserializer)
6. [Complete Example](#complete-example)

---

## The Serializable Interface

`Fight\Common\Domain\Serialization\Serializable`

Domain objects implement this interface to declare they can be serialized. It has two methods:

```php
interface Serializable
{
    public static function arrayDeserialize(array $data): static;
    public function arraySerialize(): array;
}
```

- `arraySerialize()` returns the object's state as a plain associative array.
- `arrayDeserialize()` is a named constructor that reconstructs the object from that array. It may throw `DomainException` if the data is invalid.

Implement it on any domain object — value objects, entities, or configuration models:

```php
use Fight\Common\Domain\Serialization\Serializable;

final readonly class Coordinate implements Serializable
{
    private function __construct(
        public float $lat,
        public float $lng
    ) {}

    public static function fromString(string $value): static
    {
        // ...
    }

    public static function arrayDeserialize(array $data): static
    {
        return new static($data['lat'], $data['lng']);
    }

    public function arraySerialize(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}
```

---

## The Serializer Interface

`Fight\Common\Domain\Serialization\Serializer`

The mechanism that converts `Serializable` objects to and from strings:

```php
interface Serializer
{
    public function serialize(Serializable $object): string;
    public function deserialize(string $state): Serializable;
}
```

- `serialize()` takes any `Serializable` object and returns a string representation.
- `deserialize()` takes that string and returns a new instance of the original object.

The `Serializer` is format-agnostic — two implementations ship with the library.

---

## Envelope Format

Both serializers wrap the object's data in a structured envelope with two keys:

```php
[
    '@' => 'App.Dto.Coordinate',       // canonical class name (dots, not backslashes)
    '$' => ['lat' => 48.85, 'lng' => 2.35],  // the arraySerialize() result
]
```

- **`@`** — identifies which class to reconstruct during deserialization, stored as a canonical (dot-separated) class name.
- **`$`** — the object's serialized state, exactly as returned by `arraySerialize()`.

During deserialization, the serializer reads `@`, resolves the class via `ClassName::full()`, verifies it implements `Serializable`, and calls `$class::arrayDeserialize($data['$'])`.

---

## JsonSerializer

`Fight\Common\Domain\Serialization\JsonSerializer`

Converts to and from JSON strings. Uses `JSON_UNESCAPED_SLASHES` as the default encoding.

```php
use Fight\Common\Domain\Serialization\JsonSerializer;

$serializer = new JsonSerializer();

// Serialize
$json = $serializer->serialize($coordinate);
// '{"@":"App.Dto.Coordinate","$":{"lat":48.85,"lng":2.35}}'

// Deserialize
$restored = $serializer->deserialize($json);
// Coordinate instance
```

Suitable for API payloads, message queues, database JSON columns, or any interop scenario where the string needs to be human-readable and language-independent.

Throws `DomainException` when:
- The JSON string cannot be parsed.
- The envelope is missing the `@` or `$` key.
- The resolved class does not implement `Serializable`.

---

## PhpSerializer

`Fight\Common\Domain\Serialization\PhpSerializer`

Uses PHP's native `serialize()` and `unserialize()` with the same envelope format. The output is PHP-specific and more compact.

```php
use Fight\Common\Domain\Serialization\PhpSerializer;

$serializer = new PhpSerializer();

$serialized = $serializer->serialize($coordinate);
// a:2:{s:1:"@";s:18:"App.Dto.Coordinate";s:1:"$";a:2:{s:3:"lat";d:48.85;s:3:"lng";d:2.35;}}

$restored = $serializer->deserialize($serialized);
// Coordinate instance
```

Suitable for PHP-internal use cases: cache backends, session storage, or any context where cross-language compatibility is not needed.

Same validation as `JsonSerializer` — missing keys or non-`Serializable` classes throw `DomainException`.

---

## Complete Example

A richer domain object that composes another `Serializable` and handles nested deserialization:

```php
use Fight\Common\Domain\Serialization\JsonSerializer;
use Fight\Common\Domain\Serialization\Serializable;

final readonly class CustomerProfile implements Serializable
{
    public function __construct(
        public string $name,
        public EmailAddress $email,
        public int $loyaltyTier
    ) {}

    public static function arrayDeserialize(array $data): static
    {
        return new static(
            $data['name'],
            EmailAddress::fromString($data['email']),
            $data['loyaltyTier']
        );
    }

    public function arraySerialize(): array
    {
        return [
            'name'        => $this->name,
            'email'       => $this->email->toString(),
            'loyaltyTier' => $this->loyaltyTier,
        ];
    }
}

// --- Usage ---

$serializer = new JsonSerializer();

$profile = new CustomerProfile(
    'Alice',
    EmailAddress::fromString('alice@example.com'),
    3
);

$json = $serializer->serialize($profile);
// '{"@":"App.Dto.CustomerProfile","$":{"name":"Alice","email":"alice@example.com","loyaltyTier":3}}'

$restored = $serializer->deserialize($json);
// CustomerProfile instance, $restored->email is an EmailAddress value object
```

The round-trip preserves the original class and its data. The serializer handles the envelope and class resolution; the domain object controls what data goes in and how it is reconstructed.
