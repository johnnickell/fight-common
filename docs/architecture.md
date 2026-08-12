# Architecture Enforcement

Fight Common enforces its Ports and Adapters dependency direction with Deptrac. Runtime dependencies point
inward:

```text
Adapter -> Application -> Domain
```

`src/Standards` is development-time policy outside that runtime chain. Runtime layers cannot depend on it,
and Standards cannot depend on runtime code. The repository configuration also gives each layer an exact
external allowance: Domain remains framework-free, Application may use neutral PSR contracts and the exact
`CronExpression` utility, and Adapter integrations are named explicitly.

## Repository Gate

Maintainers run the repository-owned command:

```bash
./bin/deptrac
```

The command runs dependency analysis and the separate Deptrac unassigned-token check. It fails on forbidden
or uncovered dependencies, skipped violations, or any production or Standards class that is not assigned to
a layer. The repository does not use a baseline or suppressed legacy violations.

## Optional Consumer Tooling

Deptrac is a development dependency of Fight Common, not a runtime dependency. A consuming project may install
it independently when it wants to enforce its own architecture:

```bash
composer require --dev deptrac/deptrac
```

Fight Common's `deptrac.php` describes this repository's namespaces and package allowances. Consumers should
define their own layers and explicit external dependencies instead of copying that configuration unchanged.
