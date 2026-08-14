# WF-017 record-to-aggregate mapping prototype

> **PROTOTYPE — throwaway evidence, not supported Fight Common adapters or starter implementations.**

## Question

Can each selected framework persist and rehydrate one unchanged domain aggregate plus exact Role membership
without returning framework records from the repository, and which native record style should the Yii and
CodeIgniter starters select?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
php planning/wayfinder/prototypes/wf-017-record-mapping/run.php
```

The runner installs pinned dependencies, executes the same aggregate/repository probe against seven
framework candidates, and writes machine-readable receipts under `receipts/`.

## Candidate verdict

- Symfony and Slim retain adapter-owned Doctrine XML records.
- Laravel uses adapter-owned Eloquent records and `belongsToMany()->sync()`.
- Yii selects its stable Active Record package over raw Yii DB commands. The record classes remain Adapter
  details and are mapped explicitly to the aggregate; the join record remains explicit.
- CodeIgniter selects table-focused Models returning arrays over a Query Builder-only repository. The
  repository still owns aggregate and relationship composition because CodeIgniter Model is table-oriented.
- No framework record or base class crosses into the portable aggregate or repository return type.

## Deliberate limits

- SQLite is behavioral evidence only; PostgreSQL and MySQL/MariaDB migration and concurrency parity remain open.
- The comparison proves create, rehydrate, update, and exact relationship replacement, not every AccessControl
  aggregate or relationship shape.
- Transaction composition was proven by the preceding WF-017 prototypes and is not duplicated here.
- HTTP, principal integration, handler registration, realtime, and the React client remain open WF-017 lanes.
