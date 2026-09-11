---
template: atlas-article.html
atlas_article: true
title: Repositories
atlas_article_heading_id: repositories
atlas_component_group: Model the Domain
atlas_component_owner: Domain, Application, and Adapter
atlas_component_dependencies: PHP 8.5+, Domain collections, optional Doctrine ORM, Laravel, CodeIgniter, or Yii DB/DI
atlas_article_context: Domain · Application · Adapter
atlas_article_lead: Keep query shapes in the Domain, transaction intent in the Application, and database work in an adapter.
atlas_article_requires: PHP 8.5+
atlas_article_optional: doctrine/orm, laravel/framework, codeigniter4/framework, yiisoft/db, yiisoft/di
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Application port
atlas_relationship_source: TransactionalUnitOfWork
atlas_relationship_target_label: Persistence adapter
atlas_relationship_target: Doctrine, Laravel, CodeIgniter, or Yii TransactionalUnitOfWork
atlas_relationship_description: A selected persistence adapter fulfills the application transaction boundary
atlas_relationship_caption: Application code requests one atomic operation without depending on an ORM, framework connection, or database driver.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: A transaction covers only the callback's durable adapter work; external calls and outbox policy remain application responsibilities.
atlas_next_steps:
  - label: Model return values
    href: "../values/"
  - label: Work with typed records
    href: "../collections/"
  - label: Persist event-sourced aggregates
    href: "../event-sourcing/"
  - label: Coordinate commands and queries
    href: "../messaging/"
atlas_local_contents:
  - label: Pagination
    href: "#pagination"
  - label: ResultSet
    href: "#resultset"
  - label: Transactional unit of work
    href: "#transactionalunitofwork-interface"
  - label: Shipped transaction adapters
    href: "#doctrinetransactionalunitofwork"
  - label: Repository interface
    href: "#usage-in-a-repository-interface"
  - label: 1.x compatibility
    href: "#deprecated-1x-compatibility"
---

Repositories keep a Domain-facing query shape and Application transaction boundary separate from
the database technology that fulfills them. Define a repository interface in your Domain, inject it
into the use case, and bind an implementation plus `TransactionalUnitOfWork` in the composition
root.

**Ownership.** `Pagination` and `ResultSet` are Domain DTOs. `TransactionalUnitOfWork` is an
Application port. Doctrine, Laravel, CodeIgniter, and Yii transaction implementations are Adapter code; a
consumer owns its repository implementation, connection selection, migrations, and outbox policy.

**Dependencies.** The portable DTOs and port require PHP 8.5+ and this package. The optional
Doctrine adapter requires `doctrine/orm`; Laravel requires `laravel/framework`; CodeIgniter requires
`codeigniter4/framework`; and Yii requires `yiisoft/db` (plus the consumer's Yii DI setup when its
provider is used).

**Install.**

```bash
composer require johnnickell/fight-common
```

**Start with a portable application boundary.**

```php-inline
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Repository\Pagination;
use Fight\Common\Domain\Repository\ResultSet;

final readonly class ListOrders
{
    public function __construct(
        private OrderRepository $orders,
        private TransactionalUnitOfWork $unitOfWork,
    ) {}

    public function handle(): ResultSet
    {
        return $this->unitOfWork->commitTransactional(
            fn (): ResultSet => $this->orders->findAll(new Pagination(page: 1, perPage: 20)),
        );
    }
}
```

`commitTransactional()` propagates a failed callback and the chosen adapter rolls back its database
transaction. The supplied Doctrine, Laravel, CodeIgniter, and Yii adapters reject nested portable transactions
with `LogicException`; check `isClosed()` before reusing a unit of work after infrastructure
failure. Do not infer database validation or cross-system atomicity from the portable interface.

## Reference

--8<-- "docs/repositories.md"
