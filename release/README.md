# Release Module

This directory owns Fight Common's repository-maintainer release tooling. It is not part of the library's
consumer runtime API.

## Loading and distribution

Release declarations use the `Fight\Release\` namespace. The root package maps that namespace to `release/src`
only through Composer's `autoload-dev`, so maintainers can use the module from a development checkout. Composer
does not register root-package development autoloading when Fight Common is installed as a dependency. The files
may therefore be present in a distribution archive while remaining unavailable to a consumer's autoloader and
IDE completion surface.

Until T-00054 owns final production-only archive certification, Fight Common intentionally uses Composer's default
archive configuration: no custom archive name and no explicit exclusion list. The production-autoloaded content
roots are `src` and `tests/TestCase`; the maintainer-only `release` module may be exported but remains excluded from
production autoloading. This records the current package-content boundary without certifying an exact archive
member list.

## Layout

- `src/` contains release application policy, boundaries, and adapters.
- `scripts/` contains the PHP command implementation and supporting Python helpers.
- `tests/` contains release unit, conformance, process, runtime, and journey tests.
- `fixtures/` contains deterministic release and architecture fixtures.

The sole stable entry point is [`bin/release`](../bin/release). Files in `scripts/` are internal implementation
details and must not become additional top-level commands or consumer-facing entry points.

## Extending the module

Add release coordination code beneath `release/src` in the `Fight\Release\` namespace and mirror it beneath
`release/tests` in the `Fight\Test\Release\` namespace. Keep reusable release policy in `Application`, external
operations behind application boundaries, and implementations in `Adapter`. Add supporting scripts and fixtures
inside this module, and preserve the observable behavior and authority of `bin/release` unless a separate ticket
explicitly changes that contract.

Do not add release declarations beneath the production-autoloaded `src/` tree, add `Fight\Release\` to Composer's
production autoloader, expose compatibility aliases under `Fight\Common`, or create another release executable.

## Verification

Release changes must pass the canonical `./bin/build` gate. That gate explicitly checks release PHP and Python
syntax, PHPCS, PHPStan, Rector, Deptrac architecture assignment and dependency direction, the complete PHPUnit
suite, and exact 100% statement coverage across both `src/` and `release/src/`. Changes to autoloading must also
retain the development-autoload and clean consumer `--no-dev` isolation proofs.
