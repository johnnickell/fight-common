# WF-017 migration-uniqueness prototype

> **PROTOTYPE — wipeable evidence, not starter migrations or supported adapters.**

## Question

Can each selected framework migration composition create equivalent PostgreSQL and MySQL schemas that let
the database reject concurrent claims for one canonical email across account states, while relationships
continue to use `UserId`?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
bash planning/wayfinder/prototypes/wf-017-migration-uniqueness/run.sh
```

The runner builds a prototype-only child PHP image adding the native `mysqli` and `pgsql` extensions required
by CodeIgniter, installs isolated locked dependencies, starts the repository's pinned disposable MySQL 8.4
and PostgreSQL 17 containers, runs ten framework/database lanes, and removes both databases on exit. Every lane
creates the schema through its selected framework migration API, races two transactions for the same
canonical email in different account states, and writes a machine-readable receipt under `receipts/`.

## Candidate compositions

- Symfony: Doctrine DBAL schema operations behind the normal Doctrine Migrations bundle composition.
- Slim: the same Doctrine DBAL schema operations behind a project-owned Doctrine Migrations CLI.
- Laravel: Schema Builder migrations.
- Yii: Yii DB DDL command migrations, using its driver-specific MySQL and PostgreSQL packages.
- CodeIgniter: Forge migrations.

## Deliberate limits

- The schema is the minimum persistence shape needed to test identity, canonical-email uniqueness, account
  state independence, and Role relationship identity. It is not the complete AccessControl schema.
- The race proves database arbitration after one claimant writes but before it commits. It does not select
  application retry, conflict-response, or invitation idempotency policy.
- The unique index is deliberately isolated on `canonical_email`; its documented tenant evolution is a
  replacement with `(tenant_id, canonical_email)`, not a change to `UserId` or relationship keys.
- This does not prove HTTP, principal integration, handler registration, realtime authorization, the React
  client, or any full walking slice.
