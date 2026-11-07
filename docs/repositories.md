Standard DTOs for paginated repository queries (`Pagination` as input, `ResultSet` as output) and the narrow `TransactionalUnitOfWork` boundary for transaction management. Shipped adapters cover Doctrine, Laravel, CodeIgniter, and Yii.

```
Domain\Repository
├── Pagination    — input: page, perPage, orderings
└── ResultSet     — output: records + pagination metadata

Application\Repository
├── TransactionalUnitOfWork (canonical interface)
└── UnitOfWork (deprecated 1.x compatibility interface)

Adapter\Persistence
├── Doctrine\DbalTransactionalUnitOfWork
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
4. [DBAL transactional unit of work](#dbal-transactional-unit-of-work) and [DoctrineTransactionalUnitOfWork](#doctrinetransactionalunitofwork)
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

$result = ResultSet::strict(
    page: 2,
    perPage: 20,
    totalRecords: 150,
    records: $records
);

$result->page();                     // 2
$result->perPage();                  // 20
$result->totalPages();               // 8
$result->totalRecords();             // 150
$result->records();                  // same mutable ArrayList<User>
$result->itemType();                 // User::class; null for a dynamic list
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

### Strict result construction

**Contract `fight-common.behavior.result-set-strict-construction`.**
`ResultSet::strict(int $page, int $perPage, int $totalRecords, ArrayList $records): self` returns the existing
`ResultSet<T>` with method-level generic inference from `ArrayList<T>`. All four arguments are required, with
names identical to the public constructor. Construction requires `page >= 1`, `perPage >= 1`,
`totalRecords >= 0`, and a record count no greater than **either** `perPage` or `totalRecords`.
Violations raise `Fight\Common\Domain\Exception\DomainException`; diagnostic prose is not stable API.
PHP owns native int/ArrayList boundaries: wrong types from strict callers raise TypeError, while weak callers
retain native scalar coercion. This is not an HTTP parser or raw-string validator.

Zero totals require an empty list and retain **one total page**. Positive-total empty pages, partial pages,
ordinary full pages, last pages and pages beyond `totalPages` are valid. A nonempty out-of-range page is also
valid when its local count bounds hold. No count-equals-size, last-page remainder or page-position rule is
imposed; the supplied positive page and size are preserved without normalization.

For positive totals, page count is exact integer-only ceiling division: `intdiv(totalRecords - 1, perPage) + 1`.
No floating-point division or overflow-prone sum is used on this path, including during internal initialization.
Native maximum positive page, size and total values are supported without an offset calculation or arbitrary cap.
For total `PHP_INT_MAX`, sizes 1, 2 and `PHP_INT_MAX` yield respectively `PHP_INT_MAX`,
`intdiv(PHP_INT_MAX, 2) + 1` and 1 pages. Unlike strict Pagination requests, a large page/size pair cannot fail
because of an unrepresentable offset: ResultSet neither exposes nor executes one.

### Records, mutation and presentation

**Contract `fight-common.behavior.result-set-records`.** Both construction paths accept scalar, class/interface,
object and dynamic/mixed ArrayLists. Existing public `itemType(): ?string` reports the list's **declared** type
string, even when empty, or null for a dynamic list; it never infers a type from the first record.
`records()` returns the exact supplied list, with original record identity and ordering. `count()`, `isEmpty()`
and iteration delegate to that list. `toArray()` and `jsonSerialize()` retain the keys and types shown above:
integer `page`, `per_page`, `total_pages`, `total_records` and an array of unprojected `records`.

Strict validation is **construction-only**. Caller-held references and `records()` can mutate the shared list
later, even beyond initial size/total bounds; record objects likewise remain shared. Metadata does not track
those changes. There is no immutable snapshot, defensive/deep copy, continuous validation or provenance flag
on ResultSet instances. Existing ArrayList element validation is unchanged; this factory does not audit elements.

**Contract `fight-common.behavior.result-set-jsend-presentation`.**
Arrayable is **not** a construction requirement. A strict ResultSet of scalar records is legal. The existing
`JSendEnvelope::success()` presentation boundary still requires every record to implement Arrayable and projects
those records; it rejects scalar records there with InvalidArgumentException, not during ResultSet construction.
Consumers own public-safe Views/projection, request/result page-size agreement, record identity/type policy,
ordering/filtering/scope, authorization, query/provider limits and recovery. Invalid metadata grants no permission
for an unbounded fallback query. Construction performs no I/O, transaction, lock, query, event or permission decision.
It certifies neither atomic count/row reads, current totals nor complete pages; consumers own consistency guarantees.

### Legacy result construction and compatibility

**Contract `fight-common.behavior.result-set-legacy-construction`.** The public constructor remains functional
and is **not newly deprecated**. Its signatures, parameter names, permissive bounds/count behavior, shared list
and output structure are unchanged; zero size or zero total still produces one total page. No runtime notice or
automatic repository/adapter adoption is introduced, including in DoctrineAuditRepository.

The legacy path does not validate local invariants and retains its floating-point page-count limitations at
large native values (for example, total and size both `PHP_INT_MAX` can report 2 instead of 1 on 64-bit PHP).
These limitations are not repaired here or new perpetual compatibility promises. New or explicitly migrated
call sites may choose `ResultSet::strict(...)` after resolving their input/count policy. The additive factory,
DomainException failures and exact arithmetic are next-minor API/behavior under ADRs 0009–0011, not authority
to enforce validation on existing constructor users. No platform/dependency/schema, Pagination or release-policy
change follows. Direct ResultSet and JSend fixtures prove package behavior, not database isolation or downstream
consumer adoption; no exact release is assigned.

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
| `isClosed()` | Whether the unit of work is terminally unusable; a confirmed rollback need not close it |

---

## DBAL transactional unit of work

`Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork` implements the existing
`TransactionalUnitOfWork` directly using a Doctrine DBAL `Connection`, without an EntityManager or flush.
Repositories and the boundary must use the same explicitly composed connection; the unit of work supplies no
schema, capability lock, repository registry, retry or permission policy.

```php-inline
use Fight\Common\Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork;

$transactionalUnitOfWork = new DbalTransactionalUnitOfWork($connection);
$result = $transactionalUnitOfWork->commitTransactional(function () use ($accountRepository, $command) {
    return $accountRepository->apply($command);
});
```

It rejects caller-owned and nested transactions before invoking the callback, without committing or rolling back
unrelated work. DBAL auto-commit-off mode also rejects before connecting, beginning or invoking the callback:
DBAL can otherwise auto-start an outer transaction during lazy connection establishment. The boundary never
toggles that mode or commits caller work to make the connection compatible. Default lazy connections work without
additional setup. The connection must remain exclusively managed by this boundary during the callback: no manual
begin/commit/rollback, mode changes, schema operations with implicit commits or unrelated transactions. Successful
return preserves the callback result and follows the actual database commit. Every callback `Throwable` is rethrown unchanged after
confirmed rollback, and that boundary remains reusable. There is no savepoint/nested-transaction support.

Unconfirmed begin, commit or rollback raises public final
`Adapter\Persistence\Doctrine\Exception\DbalTransactionOutcomeUncertain` and terminally closes the boundary.
`originalFailure()` and the previous throwable retain the initial private diagnostic cause, including when
best-effort commit cleanup also fails. Lost commit acknowledgment never proves rollback or data absence.
Discard the closed boundary, reconcile using a fresh connection and apply the owning capability's retry rules;
reopening a connection does not reopen this unit of work. Causes/traces are not public-safe output.

These two new final declarations are public and constructible/callable, not consumer extension or implementation
points. Existing ORM and framework contracts are unchanged. Execution uses its own
[serialization/failure seam](https://johnnickell.github.io/fight-common/execution-observations/#retry-and-transaction-guarantees) around these generic mechanics;
passing a bare generic boundary to execution does not supply that lock or permit its repository writes.

---

## DoctrineTransactionalUnitOfWork

`Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork`

The Doctrine ORM adapter wraps `EntityManagerInterface` and implements only `TransactionalUnitOfWork`.

```php-inline
use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;

$transactionalUnitOfWork = new DoctrineTransactionalUnitOfWork($entityManager);

$result = $transactionalUnitOfWork->commitTransactional(function () use ($userRepository, $command) {
    $user = User::register($command->email, $command->name);
    $userRepository->save($user);

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
        private UserRepository $userRepository,
        private TransactionalUnitOfWork $transactionalUnitOfWork
    ) {}

    public function execute(RegisterUserCommand $command): void
    {
        $this->transactionalUnitOfWork->commitTransactional(function () use ($command): void {
            $user = User::register($command->email, $command->name);
            $this->userRepository->save($user);
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
