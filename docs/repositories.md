# Repositories

Standard DTOs for paginated repository queries (`Pagination` as input, `ResultSet` as output) and a `UnitOfWork` interface for transaction management. The Doctrine adapter is included.

```
Domain\Repository
├── Pagination    — input: page, perPage, orderings
└── ResultSet     — output: records + pagination metadata

Application\Repository
└── UnitOfWork (interface)

Adapter\Repository
└── DoctrineUnitOfWork
```

---

## Table of Contents

1. [Pagination](#pagination)
2. [ResultSet](#resultset)
3. [UnitOfWork Interface](#unitofwork-interface)
4. [DoctrineUnitOfWork](#doctrineunitofwork)
5. [Usage in a Repository Interface](#usage-in-a-repository-interface)

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

## UnitOfWork Interface

`Fight\Common\Application\Repository\UnitOfWork`

Abstracts transaction management so that application-layer services can flush changes or run transactional operations without coupling to a specific ORM.

```php
interface UnitOfWork
{
    public function commit(): void;
    public function commitTransactional(callable $operation): mixed;
    public function isClosed(): bool;
}
```

| Method | Purpose |
|---|---|
| `commit()` | Flushes all pending changes to the data store |
| `commitTransactional(callable)` | Wraps the operation in a transaction; returns the operation's result |
| `isClosed()` | Whether the unit of work is still usable (e.g. after a rollback) |

---

## DoctrineUnitOfWork

`Fight\Common\Adapter\Repository\DoctrineUnitOfWork`

Wraps Doctrine ORM's `EntityManagerInterface`.

```php
use Fight\Common\Adapter\Repository\DoctrineUnitOfWork;

$uow = new DoctrineUnitOfWork($entityManager);

// Flush pending changes
$uow->commit();

// Transactional operation
$result = $uow->commitTransactional(function () use ($uow) {
    // all changes are flushed inside a transaction
});
```

| Method | Delegates to |
|---|---|
| `commit()` | `$entityManager->flush()` |
| `commitTransactional($operation)` | `$entityManager->wrapInTransaction($operation)` |
| `isClosed()` | `!$entityManager->isOpen()` |

---

## Usage in a Repository Interface

The complete pattern for a repository interface using both DTOs and the `UnitOfWork`:

```php
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Application\Repository\UnitOfWork;

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
        private UnitOfWork $uow
    ) {}

    public function execute(RegisterUserCommand $command): void
    {
        $user = User::register($command->email, $command->name);
        $this->users->save($user);
        $this->uow->commit();
    }
}
```
