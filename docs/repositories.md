# Repositories

Standard DTOs for paginated repository queries (`Pagination` as input, `ResultSet` as output) and the narrow `TransactionalUnitOfWork` boundary for transaction management. The canonical Doctrine adapter is included.

```
Domain\Repository
├── Pagination    — input: page, perPage, orderings
└── ResultSet     — output: records + pagination metadata

Application\Repository
├── TransactionalUnitOfWork (canonical interface)
└── UnitOfWork (deprecated 1.x compatibility interface)

Adapter\Persistence\Doctrine
└── DoctrineTransactionalUnitOfWork (canonical adapter)

Adapter\Repository
└── DoctrineUnitOfWork (deprecated 1.x compatibility adapter)
```

---

## Table of Contents

1. [Pagination](#pagination)
2. [ResultSet](#resultset)
3. [TransactionalUnitOfWork Interface](#transactionalunitofwork-interface)
4. [DoctrineTransactionalUnitOfWork](#doctrinetransactionalunitofwork)
5. [Usage in a Repository Interface](#usage-in-a-repository-interface)
6. [Deprecated 1.x Compatibility](#deprecated-1x-compatibility)

---

## Pagination

`Fight\Common\Domain\Repository\Pagination`

An immutable input DTO for paginated repository methods. Pre-computes `offset` and `limit` from `page` and `perPage`.

```php
use Fight\Common\Domain\Repository\Pagination;

$pagination = new Pagination(
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

---

## ResultSet

`Fight\Common\Domain\Repository\ResultSet`

An output DTO wrapping a typed `ArrayList` of records together with pagination metadata. Implements `Collection` (`Countable` + `IteratorAggregate`), `Arrayable`, and `JsonSerializable`.

```php
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

json_encode($result);   // same structure
```

---

## TransactionalUnitOfWork Interface

`Fight\Common\Application\Repository\TransactionalUnitOfWork`

Defines the canonical application boundary for running a complete operation atomically without coupling application services to a specific ORM.

```php
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

The canonical Doctrine ORM adapter wraps `EntityManagerInterface` and implements only `TransactionalUnitOfWork`.

```php
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

## Usage in a Repository Interface

The complete pattern for a repository interface using both DTOs and the canonical transaction boundary:

```php
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

```php
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;

// Deprecated 1.x compatibility only.
$legacyUnitOfWork = new DoctrineUnitOfWork($entityManager);
$legacyUnitOfWork->commit();
```
