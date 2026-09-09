# ADR 0025: Thin Release Certification

- Status: accepted
- Date: 2026-09-08
- Supersedes: ADR 0014 release-command, run-state, fake-boundary, and test-framework decisions

## Decision

Fight Common retains one repository-owned release operation: `./bin/release certify <version>`. It is a
verification-only command for a clean checkout and binds its result to the exact `HEAD` commit. Release planning,
preparation, packaging state machines, effect ledgers, fake Git/GitHub/Packagist/signing boundaries, crash controls,
and fixture-driven simulated outcomes are retired.

Certification runs three independently exported product gates: locked, latest-compatible, and lowest-compatible.
It then creates a Composer archive, resolves and installs that archive in a clean `--no-dev` consumer, executes one
representative public behavior probe, proves `Fight\Release\` is absent from consumer autoloading, and compares the
installed package surface with `compatibility/manifest.json`. It cites the five immutable T-00075 starter receipt
identities without rerunning unchanged starter journeys.

One compact JSON record and the certified archive are written beneath
`.runs/handoffs/release-<version>-<full-commit>/`. The record includes command results and output digests, resolved
dependency versions, commit and archive identities, package-surface and consumer results, and cited receipt
coordinates. Certification performs no merge, tag, push, GitHub, Packagist, or deployment effect.

Everyday PHPUnit and exact statement coverage include only consumer-runtime code under `src/`. The remaining
maintainer-only release PHP stays in syntax, PHPCS, PHPStan, Rector, and Deptrac checks. Release-process PHPUnit tests
are prohibited; the release seam is verified by running the real command for an exact committed candidate.

## Consequences

Product behavior remains protected by the normal suite while expensive packaging and compatibility evidence moves
to the release boundary. A successful local build is not release certification, and certification is not
publication. Publication and Packagist verification remain separate human-authorized outcomes described by
T-00035 and T-00041.

ADRs 0013 and 0016 remain useful compatibility and publication context where they do not require the retired
framework. ADR 0014 remains the historical record for T-00032 through T-00034 and T-00040, but this decision is the
current authority when the two conflict. Maintenance automation and operator-layer tickets T-00036 through T-00039
and T-00042 through T-00043 close `wontfix`; a future need starts from a fresh decision instead of restoring the
simulation framework.

## Rejected Alternatives

Keeping the 418-method release suite was rejected because it mainly certified simulated infrastructure and made
ordinary product coverage pay for release orchestration. Removing all release tooling was rejected because an exact
archive, dependency-lane, installed-consumer, and manifest check remains valuable immediately before publication.
Combining publication with certification was rejected because local verification cannot authorize external effects.
