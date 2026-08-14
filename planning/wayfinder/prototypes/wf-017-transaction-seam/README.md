# WF-017 transaction-seam prototype

> **PROTOTYPE — throwaway evidence, not a starter implementation or supported adapter.**

## Question

Can Fight Common's unchanged `UnitOfWork::commitTransactional()` callback preserve one Application-level
login/session/audit boundary across Symfony, Laravel, Yii, CodeIgniter, and Slim using their selected native
persistence compositions?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
php planning/wayfinder/prototypes/wf-017-transaction-seam/run.php
```

The command installs each isolated dependency lock, runs one forced-commit and one forced-rollback scenario,
tests callback return values and nested-call behavior, and writes five machine-readable receipts under
`receipts/`.

## Deliberate limits

- SQLite makes the transaction behavior fast and deterministic; PostgreSQL and MySQL/MariaDB parity remains
  required before WF-017 closes.
- Symfony and Slim intentionally share one Doctrine ORM 3.6/XML-mapping dependency lane because the
  transaction and persistence mechanism under test is identical. They emit separate receipts.
- The prototype proves only the transaction center. It does not prove aggregate hydration, concurrency,
  HTTP, principal integration, realtime, the React client, or the complete AccessControl behavior.
- The record-framework adapters are disposable. They expose why `commit()` is not portable; they do not
  authorize production adapters in Fight Common or Fight AccessControl.
