# ADR 0008: Composable Local and Hosted Quality Gates

- Status: accepted
- Date: 2026-08-01

## Decision

Fight Common defines two authoritative component gates, each with a clear execution boundary and fail-fast
failure contract:

- `bin/docs validate` builds the documentation artifact with its pinned Python runtime, then validates the
  artifact, the Pages workflow contract, and its regression fixtures.
- `bin/quality` owns Composer validation, PHP and Python syntax validation, planning integrity, PHPCS,
  PHPStan, Deptrac, Rector dry-run, PHPUnit, and exact coverage enforcement in their accepted order.

Adding, removing, reordering, or changing a mandatory PHP or repository check occurs once in `bin/quality`.
The documentation artifact contract occurs once in `bin/docs validate`; neither command is a substitute for
the other.

`bin/build` is the canonical cross-runtime, non-interactive local gate for humans, agents, and hooks. It first
executes `bin/docs validate`, the exact documentation gate, before building the PHP image or provisioning
disposable databases. It then installs dependencies and runs `bin/quality` inside the disposable PHP
container. This composition is the complete local submit gate; it does not allocate a TTY or prompt for input,
and every dependency operation uses Composer's non-interactive mode. The container maps the invoking user and
group so lockfiles, installed dependencies, caches, and reports written through the mounted worktree are not
owned by root. Passing `--latest` is the explicit authorization for its documented `composer.lock` mutation.

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

Hosted evidence is intentionally split. The `Tests` workflow resolves latest-compatible dependencies
ephemerally and runs `bin/quality` directly on the hosted runner with disposable database services. The
documentation workflow runs `bin/docs validate`, uploads the resulting Pages artifact, and deploys it only
after its build job succeeds on a protected push to `main`. Both workflows must pass; neither is evidence for
the other. Hosted workflows deliberately do not execute `bin/build` or claim to run one host-neutral script:
they provide independent evidence for their respective component gate and deployment boundary.

## Failure Contract

Each component gate stops at its first failing step and returns that command's non-zero exit status. `bin/build`
does not start the PHP and repository gate when documentation validation fails, so it does not produce a mixed
summary after a prerequisite has failed. A successful local build means both component gates completed in that
invocation; successful hosted evidence means the applicable workflow completed its independent contract.

Before PHPUnit runs, `bin/quality` removes the exact Clover report path consumed by the coverage gate. A
passing coverage check therefore proves that the current PHPUnit invocation created a well-formed report rather
than accepting a stale mounted artifact from an earlier build.

Focused tests prove component ordering, fail-fast behavior, exit propagation, and local build composition. The
documentation validator fixtures prove the Pages workflow contract, so local and hosted entry points cannot
silently bypass their applicable component gate.

## Rejected Alternatives

Duplicating the ordered PHP and repository sequence in `bin/build` or GitHub Actions was rejected because
equivalent prose does not prevent implementations from drifting. `bin/quality` is the shared definition for
that component gate.

Requiring every hosted workflow to execute `bin/build` was rejected because `bin/build` is the canonical local
composition: it builds local images, provisions its local disposable runtime, and combines two component gates.
Hosted workflows instead provide narrower, independently auditable evidence at their own runner and deployment
boundaries.

Calling the existing interactive wrappers from `bin/quality` was rejected because they each rebuild the image,
allocate a TTY, and encode local-container behavior that is not part of the ordered PHP and repository gate.

Making `bin/build` interactive was rejected because the canonical submit gate must run unchanged under
agents and hooks, and its explicit flags already express the only supported dependency-resolution choice.

Using pre-push for the complete local gate was rejected because it duplicates pre-commit work at a
network-sensitive boundary and can leave a long-running validation detached from the push session.

Continuing after a failed gate was rejected because later results may be misleading when dependency,
syntax, planning, or static-analysis prerequisites are already invalid.
