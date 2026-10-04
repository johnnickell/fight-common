# Release Certification

This directory contains Fight Common's small, maintainer-only release certifier. It is development tooling,
autoloaded only through Composer's root `autoload-dev`; `Fight\Release\` is not a consumer runtime API.

## Entry point

The only release command is:

```bash
./bin/release certify <version>
```

Certification requires a clean checkout and binds all evidence to the exact `HEAD` commit. It resolves a baseline
lockfile in an exported candidate, then resolves separate latest-compatible and lowest-compatible lockfiles in
their own exported candidates, and runs the mode-free complete product gate once for each lane. The root library
checkout does not need or retain a `composer.lock`. After each exported candidate's explicit dependency
resolution, certification invokes the full build with `FIGHT_COMMON_DEPENDENCY_PROFILE=resolved`. That profile
uses `composer update --lock` to retain the resolved versions while validating lock metadata; it requires an
existing candidate lockfile. Without this boundary, the ordinary build's `composer update` would upgrade the
lowest-compatible lane back to latest-compatible versions. No product checks are skipped. Ordinary library
builds use the default profile and resolve dependency versions afresh. It then creates a Composer archive, resolves and installs that
archive with `--no-dev`, exercises one
installed-consumer behavior probe, and compares the installed package surface with `compatibility/manifest.json`.

On success it writes the archive and `certification.json` beneath
`.runs/handoffs/release-<version>-<full-commit>/`. The record includes command outcomes and output digests, exact
resolved package versions for all three lanes, the archive digest, installed-consumer and package-surface results,
and the immutable starter-receipt references accepted by T-00075.

The command performs no merge, tag, push, GitHub, Packagist, or deployment action.

## Layout and verification

- `src/` contains only code used directly by certification.
- `scripts/certify.php` is the internal helper used by `bin/release`; `scripts/functions.php` owns its pure function declarations.
- `consumer/probe.php` is the single installed-package public behavior probe; `consumer/functions.php` declares its probe function.
- `starter-receipts.json` records the immutable identities already accepted by T-00075; certification cites them
  without rerunning unchanged starter journeys.

Do not add release-process PHPUnit tests. Everyday PHPUnit and exact coverage protect only consumer-runtime code
under `src/`. Release PHP still passes syntax, PHPCS, PHPStan, Rector, and Deptrac checks through `./bin/build`, and
the expensive workflow is verified by running the real certification command for a clean committed candidate.

Do not add release declarations beneath production `src/`, map `Fight\Release\` in production autoloading, or add
another release executable.
