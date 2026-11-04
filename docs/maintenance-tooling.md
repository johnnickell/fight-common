# Maintenance-line local verification

The 1.4 checkout uses native one-off Docker containers, not develop's Compose runtime or build cache.
`./bin/build` always runs the entire gate, including ordinary Composer update. `bin/quality` passes
`--memory-limit=512M` to PHPStan explicitly so its parallel workers receive the existing per-process limit.
The focused `bin/phpstan` helper uses the same default; analysis scope and diagnostics are unchanged.
The image copies `local.ini` with mode 0644 so the gate's non-root host UID can read the existing memory and
Xdebug coverage settings even when the checkout's source file is mode 0600. No memory increase or coverage
relaxation is applied.

## One database environment across roles

`bin/build` and complete `bin/phpunit` normally create disposable MySQL/PostgreSQL databases. Python 3 and
native Docker are required on the host. The wrappers print one exact native locator:

```text
RUN_ID:NETWORK_ID:MYSQL_ID:POSTGRESQL_ID:MYSQL_VOLUME:POSTGRESQL_VOLUME
```

To retain a newly created environment through independent Review/QA, set
`FIGHT_COMMON_DATABASE_RETENTION=retain`. To reuse it, pass the printed locator as
`FIGHT_COMMON_DATABASE_RUNTIME` and keep retention explicit:

```sh
FIGHT_COMMON_DATABASE_RUNTIME="$locator" FIGHT_COMMON_DATABASE_RETENTION=retain ./bin/build
./bin/database-runtime check "$locator"
```

Do not allocate another environment merely for a new role. Focused one-off Docker checks can use the qualified
network's native name and the wrappers' existing synthetic DSNs. No persistent HTTP service is needed.
Record the locator and creation provenance in the existing TASK handoff; there is no resource registry or receipt
migration. A locator selects resources; it does not prove they belong to you or authorize deleting durable data.
An old unlabeled runtime requires explicit owner reconciliation against its creation logs and native exact IDs,
configuration, mounts and consumers before reuse/retirement. Names alone are insufficient.

## Safe retirement

After the environment is no longer needed, invoke the same owner entry point used by the wrappers' EXIT trap:

```sh
./bin/database-runtime down "$locator"
```

Default wrapper retention is `cleanup`. Teardown validates the exact IDs, matching private topology, synthetic
database configuration, no host ports/binds, and exact anonymous local data volumes. It inventories **all**
containers, including stopped consumers, before effects. Foreign consumers or unexpected configuration hold
retirement without force. It stops only the selected databases, removes stopped containers without force,
then removes their unconsumed anonymous volumes and private network. It rechecks native inventories and can be
repeated with the same locator after partial/successful retirement. Command/inventory failures are errors, not
absence; cleanup failure turns an otherwise successful wrapper into a nonzero exit, preserving an earlier
product failure. Partial startup before a complete locator is available is retained with a diagnostic identifying
the network for explicit owner inspection, never force-removed or silently ignored.

Retained mode reports retention honestly rather than claiming cleanup. Preserve foreign/shared/durable resources,
base images and builder caches. This entry point does not remove images, Git source, worktrees or branches and
does not replace the current guarded Land/source-retirement procedure. No force, prune, shared-VM shutdown or
whole-development-tooling backport is part of this maintenance correction. Qualify tooling directly with native
resources, outside product test suites.
