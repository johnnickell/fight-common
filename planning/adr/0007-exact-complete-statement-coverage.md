# ADR 0007: Zero-Exclusion Exact Statement Coverage

- Status: accepted
- Date: 2026-08-01

## Decision

Exact complete statement coverage has two simultaneous conditions: production source contains no PHPUnit
coverage-ignore directives, and the generated Clover report records covered statements equal to all
executable statements. Percentage formatting or rounding is not acceptance evidence.

T-00023 through T-00026 remove every existing `@codeCoverageIgnore`, `@codeCoverageIgnoreStart`, and
`@codeCoverageIgnoreEnd` directive from `src` in bounded migration slices before T-00027 activates the
permanent gate. Previously excluded statements are covered through deterministic tests or removed through
behavior-preserving boundary refactoring. The migration does not weaken public APIs or alter runtime behavior
solely to improve reported coverage.

Branches that currently depend on live services, operating-system processes, defensive failure conditions,
or alleged coverage-tool defects are not automatically exempt. Live dependencies move behind owned seams or
receive focused deterministic integration coverage. Tool-defect claims are reverified against the current
PHP, PHPUnit, and Xdebug versions, and obsolete workarounds are removed.

The permanent coverage gate scans `src` for forbidden directives before parsing Clover. It fails closed when
the report is missing, malformed, lacks project metrics, or reports any uncovered executable statement.
Local builds and CI invoke the same coverage-gate implementation.

## Consequences

The reported 100 percent means every maintained production statement participates in measurement and is
executed. New untestable branches cannot be hidden with inline annotations; their design or test seam must be
resolved before acceptance.

Removing the existing exclusions is visible migration work rather than hidden build-wrapper scope, so it is
split into dependency-aware tickets that all gate permanent enforcement.

## Rejected Alternatives

Trusting Clover equality alone was rejected because PHPUnit removes ignored statements before reporting its
totals, allowing an apparently exact result with unmeasured production code.

Maintaining an approved exclusion manifest was rejected because it would be a coverage baseline by another
name and would make complete coverage depend on recurring human interpretation.

Grandfathering current directives while forbidding new ones was rejected because legacy unmeasured code has
the same correctness risk as a new exclusion and would make the gate permanently less than complete.
