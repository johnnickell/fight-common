# Specification Pattern

Encapsulate business rules into reusable, composable objects. Each rule is a single class; combine them with logical operators (`and`, `or`, `not`) to build complex business logic from small, testable pieces.

```
Specification (interface)
  │  isSatisfiedBy(mixed): bool
  │  and(Specification): Specification
  │  or(Specification): Specification
  │  not(): Specification
  │
  └── CompositeSpecification (abstract)
       │  provides and(), or(), not() for free
       │
       ├── AndSpecification     -- $a && $b
       ├── OrSpecification      -- $a || $b
       ├── NotSpecification     -- !$a
       └── YourDomainRule       -- extend CompositeSpecification
```

---

## Table of Contents

1. [The Interface: Specification](#the-interface-specification)
2. [The Base: CompositeSpecification](#the-base-compositespecification)
3. [The Composites](#the-composites)
4. [Writing Your First Specification](#writing-your-first-specification)
5. [Composition Examples](#composition-examples)
6. [The Evaluation Tree](#the-evaluation-tree)
7. [Best Practices](#best-practices)
8. [Complete Example: Order Discount Eligibility](#complete-example-order-discount-eligibility)

---

## The Interface: Specification

`Fight\Common\Domain\Specification\Specification`

Every specification implements this contract:

```php
interface Specification
{
    public function isSatisfiedBy(mixed $candidate): bool;
    public function and(Specification $other): Specification;
    public function or(Specification $other): Specification;
    public function not(): Specification;
}
```

The single evaluation method `isSatisfiedBy` takes a candidate (any domain object) and returns `true` or `false`. The three combinator methods return new composite specifications, enabling fluent chaining.

---

## The Base: CompositeSpecification

`Fight\Common\Domain\Specification\CompositeSpecification`

Extend this abstract class to create your business rules. You only need to implement `isSatisfiedBy` — the three combinator methods are provided for free:

```php
abstract class CompositeSpecification implements Specification
{
    abstract public function isSatisfiedBy(mixed $candidate): bool;

    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): Specification
    {
        return new NotSpecification($this);
    }
}
```

Each combinator returns a new composite node that wraps `$this` with `$other`. Nothing is mutated — the tree is immutable.

---

## The Composites

Three concrete classes implement the boolean operations. All are `final` — you compose them via the combinator methods, not by extending them.

| Class | Operator | Behavior |
|---|---|---|
| `AndSpecification` | `&&` | Both sub-specifications must be satisfied |
| `OrSpecification` | `||` | At least one sub-specification must be satisfied |
| `NotSpecification` | `!` | The wrapped specification must **not** be satisfied |

Internally they are simple wrappers around PHP's native operators:

```php
// AndSpecification
public function isSatisfiedBy(mixed $candidate): bool
{
    return $this->firstSpec->isSatisfiedBy($candidate)
        && $this->secondSpec->isSatisfiedBy($candidate);
}

// OrSpecification
public function isSatisfiedBy(mixed $candidate): bool
{
    return $this->firstSpec->isSatisfiedBy($candidate)
        || $this->secondSpec->isSatisfiedBy($candidate);
}

// NotSpecification
public function isSatisfiedBy(mixed $candidate): bool
{
    return !$this->spec->isSatisfiedBy($candidate);
}
```

---

## Writing Your First Specification

### Ad-Hoc Rule (Anonymous Class)

For one-off checks in tests or simple filters, create an anonymous class extending `CompositeSpecification`:

```php
$isPremium = new class extends CompositeSpecification {
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->tier === 'premium';
    }
};

$isPremium->isSatisfiedBy($user);  // true or false
```

### Named Rule (Reusable)

Extract the logic into a named class when the rule is used in multiple places or needs configuration:

```php
use Fight\Common\Domain\Specification\CompositeSpecification;

class PremiumCustomerSpecification extends CompositeSpecification
{
    public function __construct(
        private readonly float $minAnnualRevenue
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (!($candidate instanceof Customer)) {
            return false;
        }

        return $candidate->tier === 'premium'
            && $candidate->annualRevenue >= $this->minAnnualRevenue;
    }
}

$spec = new PremiumCustomerSpecification(100000.0);
$spec->isSatisfiedBy($customer);
```

The `$candidate` has no type constraint on the interface — use `instanceof` or `assert` in your implementation to guard.

---

## Composition Examples

Build complex business rules from small, single-responsibility leaf specs.

### Leaf Specifications

Each checks one thing:

```php
class IsAdminSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return in_array('admin', $candidate->roles, true);
    }
}

class IsActiveSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->status === 'active';
    }
}

class IsBannedSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->status === 'banned';
    }
}

class TeamNotFullSpecification extends CompositeSpecification
{
    public function __construct(private readonly int $maxMembers)
    {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return count($candidate->members) < $this->maxMembers;
    }
}
```

### And / Or

Combine them fluently:

```php
// User must be admin AND active
$canAccessAdmin = (new IsAdminSpecification())
    ->and(new IsActiveSpecification());

// User must have a role: editor OR admin
$hasEditorialRole = (new IsEditorSpecification())
    ->or(new IsAdminSpecification());
```

### Negation

```php
// User must be active AND NOT banned
$canAccess = (new IsActiveSpecification())
    ->and(new IsBannedSpecification()->not());
```

### Chained Composition

```php
// Admin or editor, active, and not banned
$canPublish = (new IsAdminSpecification())
    ->or(new IsEditorSpecification())
    ->and(new IsActiveSpecification())
    ->and(new IsBannedSpecification()->not());
```

### Parameterized Rules

```php
// Not full AND (admin OR editor) AND active AND not banned
$canInviteMember = (new TeamNotFullSpecification(10))
    ->and(new IsAdminSpecification())
    ->or(new IsEditorSpecification())
    ->and(new IsActiveSpecification())
    ->and(new IsBannedSpecification()->not());
```

---

## The Evaluation Tree

Each combinator creates a node in a tree. When `isSatisfiedBy` is called, the tree is traversed depth-first.

```php
$spec = (new IsAdminSpecification())
    ->or(new IsEditorSpecification())
    ->and(new IsActiveSpecification());
```

Produces this structure:

```
AndSpecification
  ├── OrSpecification
  │     ├── IsAdminSpecification
  │     └── IsEditorSpecification
  └── IsActiveSpecification
```

Evaluation for a user who is an editor and active:

```
spec.isSatisfiedBy($user)
  └─► AndSpecification
        ├─► OrSpecification
        │     ├─► IsAdmin.isSatisfiedBy($user)   → false
        │     └─► IsEditor.isSatisfiedBy($user)  → true
        │     result: true
        └─► IsActive.isSatisfiedBy($user)         → true
        result: true
```

The `not()` combinator wraps its inner spec in a `NotSpecification` node that inverts the result:

```
NotSpecification
  └── IsBannedSpecification
```

---

## Best Practices

- **One rule per class.** A specification should check a single business concept. Compose narrow specs to build broad rules.
- **Name after the business concept.** `PremiumCustomerSpecification` not `HighRevenueAndTierSpecification`.
- **Guard the candidate type.** The interface accepts `mixed`; use `instanceof` or `assert` at the top of `isSatisfiedBy`.
- **Use anonymous classes for test stubs.** When a test needs a specification that always returns a known value, an anonymous class is cleaner than a mock.
- **Keep specs in the domain layer.** Specifications express business rules — they belong in `src/Domain/Specification/` alongside your entities.
- **Parameterize through the constructor.** Let callers configure thresholds, limits, or collections rather than hard-coding them.

---

## Complete Example: Order Discount Eligibility

A realistic domain rule: an order qualifies for a promotional discount when the customer is a loyalty member, the order total meets a minimum, the coupon code is valid, and the current date falls within the promotion window.

### Leaf Specifications

```php
class LoyalCustomerSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->customer->loyaltyTier >= 2;
    }
}

class MinimumOrderSpecification extends CompositeSpecification
{
    public function __construct(private readonly float $minTotal)
    {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->total >= $this->minTotal;
    }
}

class ValidCouponSpecification extends CompositeSpecification
{
    /** @var string[] */
    private readonly array $validCoupons;

    public function __construct(string ...$validCoupons)
    {
        $this->validCoupons = $validCoupons;
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return in_array($candidate->couponCode, $this->validCoupons, true);
    }
}

class DateRangeSpecification extends CompositeSpecification
{
    public function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end
    ) {
    }

    public function isSatisfiedBy(mixed $candidate): bool
    {
        $now = new DateTimeImmutable();
        return $now >= $this->start && $now <= $this->end;
    }
}
```

### Composed Rule

```php
$eligibleForDiscount = (new LoyalCustomerSpecification())
    ->and(new MinimumOrderSpecification(50.0))
    ->and(new ValidCouponSpecification('SAVE20', 'WELCOME10'))
    ->and(new DateRangeSpecification(
        new DateTimeImmutable('2026-06-01'),
        new DateTimeImmutable('2026-09-01')
    ));

if ($eligibleForDiscount->isSatisfiedBy($order)) {
    // apply discount
}
```

### Reuse Through Composition

The same leaf specs can be combined differently for other business rules:

```php
// Early-access program: loyal OR has a special coupon
$earlyAccess = (new LoyalCustomerSpecification())
    ->or(new ValidCouponSpecification('EARLY2026'));

// VIP treatment: loyal AND high-value, regardless of coupon
$vipTreatment = (new LoyalCustomerSpecification())
    ->and(new MinimumOrderSpecification(200.0));
```

Each leaf spec is independently testable and can be composed in any combination without modification — the pattern grows with your domain logic rather than requiring new classes for every permutation.
