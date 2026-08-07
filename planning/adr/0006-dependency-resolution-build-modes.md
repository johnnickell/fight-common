# ADR 0006: Persistent Local and Ephemeral CI Dependency Resolution

- Status: accepted
- Date: 2026-08-01

## Decision

The default local `bin/build` installs and verifies the dependency resolution recorded in the checked-in
`composer.lock`. It does not update that resolution. This is the reproducible path used for ordinary local
development and release verification.

`bin/build --latest` is an explicit dependency-update workflow. It runs Composer update with all required
dependency movement, writes the resulting `composer.lock` into the mounted worktree, and then runs the
complete quality pipeline against that exact resolution. The updated lockfile remains visible for review and
an intentional commit. A failed quality gate does not automatically restore the previous lockfile; the user
owns the decision to repair, retain, or revert the dependency update.

CI does not use the checked-in lockfile as its dependency-compatibility proof. It resolves the latest versions
permitted by `composer.json` in the ephemeral runner and executes the same authoritative gates directly on
the host. Its generated lockfile is evidence for that run only and is not published or committed.

Both modes validate `composer.json` and the applicable resolution before continuing. Local and CI
documentation clearly distinguish reproducible locked verification from latest-compatible verification.

## Consequences

An explicit local `--latest` invocation is allowed to modify a tracked file. That mutation is deliberate and
recoverable through normal review and version control rather than hidden in a temporary container layer.
Ordinary `bin/build` remains non-mutating with respect to dependency resolution.

CI can reveal compatibility failures hidden by an older checked-in lockfile, while local development can
reproduce the accepted resolution exactly. A green CI update does not silently change the project's release
dependencies; adopting the generated resolution still requires a reviewed local lockfile update.

## Rejected Alternatives

Running local `--latest` against a temporary untracked lockfile was rejected because the verified resolution
would disappear after the build and could not be reviewed or intentionally committed without repeating the
operation.

Automatically restoring `composer.lock` after a failed `--latest` build was rejected because it would hide
the exact failing resolution needed for diagnosis.

Using only `composer install` in CI was rejected because it proves the checked-in resolution but does not
verify compatibility with the newest versions allowed by the package constraints.
