Two cooperating layers keep serialization boundaries explicit: the Domain `Serializable` interface
lets objects supply and restore their state, while canonical Application `Serializer`
implementations convert that state to and from string formats. The legacy concrete serializers in
`Domain\Serialization` are deprecated 1.x compatibility classes, not the recommended API.

```
Serializable (interface)
  │  arraySerialize(): array
  │  arrayDeserialize(array $data): static
  │
  └── YourDomainObject implements Serializable

Serializer (Domain interface)
  │  serialize(Serializable $object): string
  │  deserialize(string $state): Serializable
  │
  └── Application\Serialization\JsonSerializer   → JSON strings
      Application\Serialization\PhpSerializer    → PHP-serialized strings
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

```php-inline
interface Serializable
{
    public static function arrayDeserialize(array $data): static;
    public function arraySerialize(): array;
}
```

- `arraySerialize()` returns the object's state as a plain associative array.
- `arrayDeserialize()` is a named constructor that reconstructs the object from that array. It may throw `DomainException` if the data is invalid.

Implement it on any domain object — value objects, entities, or configuration models:

```php-inline
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

```php-inline
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

```php-inline
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

`Fight\Common\Application\Serialization\JsonSerializer`

Converts to and from JSON strings. Uses `JSON_UNESCAPED_SLASHES` as the default encoding.

```php-inline
use Fight\Common\Application\Serialization\JsonSerializer;

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

`Fight\Common\Application\Serialization\PhpSerializer`

Uses PHP's native `serialize()` and `unserialize()` with the same envelope format. The output is PHP-specific and more compact.

```php-inline
use Fight\Common\Application\Serialization\PhpSerializer;

$serializer = new PhpSerializer();

$serialized = $serializer->serialize($coordinate);
// a:2:{s:1:"@";s:18:"App.Dto.Coordinate";s:1:"$";a:2:{s:3:"lat";d:48.85;s:3:"lng";d:2.35;}}

$restored = $serializer->deserialize($serialized);
// Coordinate instance
```

Suitable only for trusted PHP-internal use cases where cross-language compatibility is not needed.
Because it calls PHP `unserialize()`, never use it on untrusted input.

Same validation as `JsonSerializer` — missing keys or non-`Serializable` classes throw `DomainException`.

---

## Complete Example

A richer domain object that composes another `Serializable` and handles nested deserialization:

```php-inline
use Fight\Common\Application\Serialization\JsonSerializer;
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
