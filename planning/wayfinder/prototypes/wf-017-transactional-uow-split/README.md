# WF-017 TransactionalUnitOfWork split prototype

> **PROTOTYPE — throwaway evidence, not a supported Fight Common contract or adapter.**

## Question

Can an additive 1.x `TransactionalUnitOfWork` port preserve unchanged legacy Doctrine consumers while
allowing record-oriented Laravel, Yii, and CodeIgniter adapters to omit `commit()` entirely, with one
framework-neutral Application transaction function across all five starter compositions?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
php planning/wayfinder/prototypes/wf-017-transactional-uow-split/run.php
```

The runner installs the pinned dependency locks from the preceding transaction-seam prototype, executes the
candidate split against the real Doctrine, Illuminate Database, Yii DB, and CodeIgniter Database APIs, and
writes five machine-readable receipts under `receipts/`.

## Candidate shape

- additive `TransactionalUnitOfWork` declares `commitTransactional()` and `isClosed()`;
- legacy `UnitOfWork extends TransactionalUnitOfWork` and retains `commit()` through 1.x;
- Doctrine implements legacy `UnitOfWork`, so existing consumers can still flush pending entities;
- record-oriented adapters implement only `TransactionalUnitOfWork` and expose no `commit()` method;
- portable Application code type-hints only `TransactionalUnitOfWork` and runs unchanged in every lane.

## Deliberate limits

- The contracts and adapters are disposable prototype namespaces. Production `src/` remains unchanged.
- This proves the source-compatible interface and adapter shape, not container aliases for every framework.
- The transaction behavior remains SQLite evidence; PostgreSQL and MySQL/MariaDB parity is still open.
- Aggregate hydration, concurrency, HTTP, principal integration, realtime, and the React client remain open
  WF-017 lanes.
