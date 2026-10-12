Standard DTOs for paginated repository queries (`Pagination` as input, `ResultSet` as output) and the narrow `TransactionalUnitOfWork` boundary for transaction management. Shipped adapters cover Doctrine, Laravel, CodeIgniter, and Yii.

```
Domain\Repository
├── Pagination    — input: page, perPage, orderings
└── ResultSet     — output: records + pagination metadata

Application\Repository
├── TransactionalUnitOfWork (canonical interface)
└── UnitOfWork (deprecated 1.x compatibility interface)

Adapter\Persistence
├── Doctrine\DoctrineTransactionalUnitOfWork
├── Laravel\LaravelTransactionalUnitOfWork
├── CodeIgniter\CodeIgniterTransactionalUnitOfWork
└── Yii\YiiTransactionalUnitOfWork

Adapter\Repository
└── DoctrineUnitOfWork (deprecated 1.x compatibility adapter)
```

---

## Table of Contents

1. [Pagination](#pagination)
2. [ResultSet](#resultset)
3. [TransactionalUnitOfWork Interface](#transactionalunitofwork-interface)
4. [DoctrineTransactionalUnitOfWork](#doctrinetransactionalunitofwork)
5. [CodeIgniterTransactionalUnitOfWork](#codeignitertransactionalunitofwork)
6. [Laravel and Yii transactional adapters](#laravel-and-yii-transactional-adapters)
7. [Usage in a Repository Interface](#usage-in-a-repository-interface)
8. [Deprecated 1.x Compatibility](#deprecated-1x-compatibility)

---

## Pagination

`Fight\Common\Domain\Repository\Pagination`

An immutable input DTO for paginated repository methods. Pre-computes `offset` and `limit` from `page` and `perPage`.

```php-inline
use Fight\Common\Domain\Repository\Pagination;

$pagination = Pagination::strict(
    page: 2,
    perPage: 20,
    orderings: ['createdAt' => 'DESC', 'name' => 'ASC']
);

$pagination->page();                 // 2
$pagination->perPage();              // 20
$pagination->offset();               // 20
$pagination->limit();                // 20
$pagination->orderings();            // ['createdAt' => 'DESC', 'name' => 'ASC']
```

| Method | Returns | Notes |
|---|---|---|
| `page()` | `int` | Defaults to `Pagination::DEFAULT_PAGE` (1) |
| `perPage()` | `int` | Defaults to `Pagination::DEFAULT_PER_PAGE` (100) |
| `offset()` | `int` | Computed: `(page - 1) * perPage` |
| `limit()` | `int` | Same as `perPage` |
| `orderings()` | `array` | Values normalized to `ASC` / `DESC` |

Constants: `Pagination::ASC`, `Pagination::DESC`, `Pagination::DEFAULT_PAGE`, `Pagination::DEFAULT_PER_PAGE`.

### Strict pagination construction

**Contract `fight-common.behavior.pagination-strict-construction`.**
`Pagination::strict(?int $page = null, ?int $perPage = null, array $orderings = []): self` returns the existing
immutable `Pagination`, accepted by existing repository signatures without adapter changes. Omitted or explicit
null bounds select page 1 and size 100 independently. Resolved bounds must be positive; zero and negative values
raise `Fight\Common\Domain\Exception\DomainException` rather than selecting defaults.

Offset is exactly `(page - 1) * perPage`; limit is `perPage`. The factory checks integer representability before
multiplication and raises the same DomainException family on overflow, not an accidental float-assignment TypeError.
There is no arbitrary page-size cap: page one with `PHP_INT_MAX` size has zero offset, and page two with that size
has offset `PHP_INT_MAX`. A database or consumer may impose smaller limits.

Ordering values must be strings equal to ASC or DESC case-insensitively. They normalize to uppercase, retain their
field associations and sequence, and default to an empty array. Unknown, empty, padded or non-string direction values
raise DomainException; the factory neither trims nor silently substitutes ASC. Exception prose is not stable API.

PHP owns the `?int` and `array` parameter boundaries: wrong argument types from strict PHP callers raise native
TypeError, not DomainException. Weak callers retain PHP's native scalar coercion; this API is not an HTTP parser.
Parse and validate external strings before calling it. Direction values are checked explicitly even for weak callers.

Construction performs no I/O or query. Consumers retain ordering-field eligibility, SQL identifier safety, authorization,
query execution and provider restrictions. Positive bounds are not a query permission or a promise that a provider
can execute them. Neither input nor returned ordering-array edits change a constructed Pagination.

### Legacy pagination and migration

**Contract `fight-common.behavior.pagination-legacy-construction`.** The existing public constructor remains
functional: omitted/null/zero bounds select the defaults, ordinary positive bounds compute the same offset/limit,
and directions normalize case-insensitively with unknown strings becoming ASC. Existing consumers do not have to
adopt the factory in the minor release. Separate legacy tests retain these expectations.

Legacy construction is deprecated in PHPDoc/documentation only, with **no runtime deprecation warning**. It does
not enforce positive bounds or check overflow: negative bounds can produce negative offsets/limits, and an
unrepresentable offset can fail with TypeError. These reproduced limitations are not repaired by this addition and
are not new perpetual compatibility promises.

For new or migrated call sites, replace `new Pagination(...)` with `Pagination::strict(...)`, retaining the same
parameter names. Normalize intentional zero-as-default inputs to null explicitly and resolve unsupported directions
in consumer input policy before opting in. Handle DomainException for invalid resolved values, not as permission to
run an unbounded fallback query. Existing repository accessors, ResultSet metadata and adapter signatures are unchanged.
Incompatible enforcement/removal of legacy construction requires a separately authorized major transition after at
least one released minor of functional deprecation support; no exact release or automatic migration is assigned.

The factory and its validation failures are additive minor API/behavior under ADRs 0009–0011. No database schema,
persisted representation, framework support range, dependency or legacy constructor behavior changes. Direct value
fixtures prove strict and legacy contracts; a real strict Pagination with controlled Doctrine collaborators proves
adapter translation and result metadata, not real database execution or installed-starter qualification.

---

## ResultSet

`Fight\Common\Domain\Repository\ResultSet`

An output DTO wrapping a typed `ArrayList` of records together with pagination metadata. Implements `Collection` (`Countable` + `IteratorAggregate`), `Arrayable`, and `JsonSerializable`.

```php-inline
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Collection\ArrayList;

$records = ArrayList::of(User::class);
$records->add($user1);
$records->add($user2);

$result = new ResultSet(
    page: 2,
    perPage: 20,
    totalRecords: 150,
    records: $records
);

$result->page();                     // 2
$result->perPage();                  // 20
$result->totalPages();               // 8
$result->totalRecords();             // 150
$result->records();                  // ArrayList<User>
$result->isEmpty();                  // false
$result->count();                    // 2

// Implements Collection — iterable
foreach ($result as $user) { /* ... */ }

// Serializable
$result->toArray();
// [
//     'page'          => 2,
//     'per_page'      => 20,
//     'total_pages'   => 8,
//     'total_records' => 150,
//     'records'       => [ ... ]
// ]

json_encode($result);                // same structure
```

---

## TransactionalUnitOfWork Interface

`Fight\Common\Application\Repository\TransactionalUnitOfWork`

Defines the canonical application boundary for running a complete operation atomically without coupling application services to a specific ORM.

```php-inline
interface TransactionalUnitOfWork
{
    public function commitTransactional(callable $operation): mixed;
    public function isClosed(): bool;
}
```

| Method | Purpose |
|---|---|
| `commitTransactional(callable)` | Wraps the operation in a transaction; returns the operation's result |
| `isClosed()` | Whether the unit of work is still usable (e.g. after a rollback) |

---

## DoctrineTransactionalUnitOfWork

`Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork`

The Doctrine ORM adapter wraps `EntityManagerInterface` and implements only `TransactionalUnitOfWork`.

```php-inline
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;

$unitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

$result = $unitOfWork->commitTransactional(function () use ($users, $command) {
    $user = User::register($command->email, $command->name);
    $users->save($user);

    return $user->id();
});
```

| Method | Delegates to |
|---|---|
| `commitTransactional($operation)` | `$entityManager->wrapInTransaction($operation)` |
| `isClosed()` | `!$entityManager->isOpen()` |

---

## CodeIgniterTransactionalUnitOfWork

`Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork` adapts one explicitly
selected CodeIgniter database connection to `TransactionalUnitOfWork`. Register it only from the project-owned
`Config\Services` persistence capability delegate; selecting messaging does not bind it.

```php-inline
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\PersistenceServices;

return PersistenceServices::transactionalUnitOfWork(db_connect());
```

The adapter begins, checks, commits, and rolls back the native transaction around one callback. It rejects nested
portable transactions. Connection selection, transaction-exception policy, and migrations remain application
configuration. Any outbox remains application configuration.

---

## Laravel and Yii transactional adapters

`Fight\Common\Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork` wraps one Laravel
`Illuminate\Database\Connection`; the shipped Laravel `PersistenceServiceProvider` binds it to
`TransactionalUnitOfWork` using the application's `db.connection`. It requires `laravel/framework`.

`Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork` wraps a Yii
`Yiisoft\Db\Connection\ConnectionInterface`; the shipped Yii `PersistenceServiceProvider` returns the
corresponding DI definition. It requires `yiisoft/db` and the consumer's Yii DI configuration.

Both adapters reject nested portable transactions. Yii also rejects execution on a connection it has observed as
closed; consumers still own connection selection, migration, retry, and outbox policy.

---

## Usage in a Repository Interface

The complete pattern for a repository interface using both DTOs and the canonical transaction boundary:

```php-inline
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;

interface UserRepository
{
    public function find(UserId $id): ?User;
    public function findAll(Pagination $pagination): ResultSet;
    public function save(User $user): void;
    public function remove(UserId $id): void;
}

class RegisterUserService
{
    public function __construct(
        private UserRepository $users,
        private TransactionalUnitOfWork $unitOfWork
    ) {}

    public function execute(RegisterUserCommand $command): void
    {
        $this->unitOfWork->commitTransactional(function () use ($command): void {
            $user = User::register($command->email, $command->name);
            $this->users->save($user);
        });
    }
}
```

---

## Deprecated 1.x compatibility

`Fight\Common\Application\Repository\UnitOfWork` and
`Fight\Common\Adapter\Repository\DoctrineUnitOfWork` remain functional throughout 1.x without runtime
deprecation notices. Their standalone `UnitOfWork::commit()` journey is deprecated 1.x compatibility, not the
path for new consumers. Migrate new and existing transaction boundaries to `TransactionalUnitOfWork` and
`DoctrineTransactionalUnitOfWork`:

```php-inline
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;

// Deprecated 1.x compatibility only.
$legacyUnitOfWork = new DoctrineUnitOfWork($entityManager);
$legacyUnitOfWork->commit();
```
