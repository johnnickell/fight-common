# WF-017 refresh rotation concurrency prototype

> **PROTOTYPE — wipeable database-locking evidence, not a supported session adapter.**

## Question

Can one unchanged refresh-session rotation operation use the selected native transaction APIs in Symfony,
Laravel, Yii, CodeIgniter, and Slim so that two concurrent uses of one credential produce one successor,
one bounded conflict, and later replay revokes only the affected device family?

## Run

From the Fight Common repository root:

```bash
bash planning/wayfinder/prototypes/wf-017-refresh-rotation-concurrency/run.sh
```

The runner starts the repository's disposable PostgreSQL 17 and MySQL 8.4 services and executes ten lanes.
Each lane forces two separate PHP processes to contend on the same refresh-session row and writes a
machine-readable receipt under `receipts/`.

## Passing verdict

All lanes use a row lock inside one native transaction. The winner rotates the predecessor, inserts exactly
one successor, and writes the rotation audit atomically. The concurrent loser waits for that commit and
returns a bounded conflict without creating a second successor or revoking the family. Reuse after the
five-second prototype conflict window revokes the affected device family and writes a reuse audit. A forced
audit failure rolls the candidate rotation back completely.

The portable operation contains no framework branch. Symfony and Slim compose Doctrine DBAL, Laravel uses
Illuminate Database, Yii uses Yii DB, and CodeIgniter uses its explicit transaction API.

## Deliberate limits

- Five seconds is an illustrative conflict window, not a production support guarantee.
- A bounded conflict returns no successor credential. The accepted client single-flight coordination owns
  recovery from a concurrent refresh in one browser context.
- The probe uses one database host and separate processes, but it does not test multi-region replication,
  failover, connection loss during commit, or production isolation-level configuration.
- It does not boot full framework kernels or prove cookie middleware, CSRF, JWT issuance, browser tab
  coordination, routes, or the five complete login walking slices.
- No Fight Common or Fight AccessControl production change is justified by this prototype.
