# ADR 0008: Shared Executable Local and CI Quality Gate

- Status: accepted
- Date: 2026-08-01

## Decision

One host-neutral executable script is the authoritative ordered definition of Fight Common's complete
quality gate. It runs fail-fast and emits a visible name before each step so the first failed contract is
unambiguous.

The script owns Composer validation, PHP syntax validation, planning integrity, PHPCS, PHPStan, Deptrac,
Rector dry-run, PHPUnit, and exact coverage enforcement in their accepted order. Adding, removing,
reordering, or changing a mandatory gate occurs once in this script.

Local `bin/build` builds the PHP image once and invokes the shared script inside one disposable container.
CI prepares its ephemeral latest-compatible dependency resolution and invokes the same script directly on
the hosted runner without Docker. The execution environment differs, but the gate definition and ordering do
not.

`bin/build` is the canonical non-interactive local entry point for humans, agents, and hooks. It does not
allocate a TTY or prompt for input, and every dependency operation uses Composer's non-interactive mode. The
container maps the invoking user and group so lockfiles, installed dependencies, caches, and reports written
through the mounted worktree are not owned by root. Passing `--latest` is the explicit authorization for its
documented `composer.lock` mutation.

Individual `bin/phpcs`, `bin/phpstan`, `bin/rector`, `bin/phpunit`, and similar wrappers remain useful for
interactive focused work. They are not submit-gate definitions and do not orchestrate or duplicate the
complete sequence.

Fight Common tracks `.githooks/pre-commit` as an opt-in local enforcement point. The hook resolves the
repository root, disconnects stdin, and delegates exactly to the default `./bin/build`, propagating its exit
status so any failed gate blocks the commit. Contributors enable it through
`git config core.hooksPath .githooks`; repository documentation explains activation and Git's explicit
`--no-verify` escape hatch.

The complete gate does not run again at pre-push. A long push-time hook can disrupt remote authentication or
network sessions, while pre-commit provides the same local evidence before the commit exists. Hosted CI
remains an independent latest-compatible verification surface rather than the first place ordinary failures
are discovered.

## Failure Contract

The shared script stops at the first failing gate and returns that command's non-zero exit status. It does
not continue to produce a mixed summary after a prerequisite has failed. Successful completion means every
ordered gate ran in that invocation.

Before PHPUnit runs, the script removes the exact Clover report path consumed by the coverage gate. A passing
coverage check therefore proves that the current PHPUnit invocation created a well-formed report rather
than accepting a stale mounted artifact from an earlier build.

Focused tests prove command ordering, fail-fast behavior, exit propagation, and build-wrapper delegation so
local and CI entry points cannot silently bypass the shared contract.

## Rejected Alternatives

Maintaining a shell sequence in `bin/build` and a separate YAML sequence in GitHub Actions was rejected
because equivalent prose does not prevent the implementations from drifting.

Calling the existing interactive wrappers from the shared script was rejected because they each rebuild the
image, allocate a TTY, and encode local-container behavior that cannot run directly in CI.

Making `bin/build` interactive was rejected because the canonical submit gate must run unchanged under
agents and hooks, and its explicit flags already express the only supported dependency-resolution choice.

Using pre-push for the complete local gate was rejected because it duplicates pre-commit work at a
network-sensitive boundary and can leave a long-running validation detached from the push session.

Continuing after a failed gate was rejected because later results may be misleading when dependency,
syntax, planning, or static-analysis prerequisites are already invalid.
