# ADR 0010: Behavioral Contract Authority

- Status: accepted
- Date: 2026-08-06

## Decision

Fight Common compatibility includes explicit consumer-facing behavior, not only PHP declarations.
Claims in the published `1.1.0` README, documentation, and public PHPDoc are presumptively binding.
Accepted ADRs define behavioral contracts from their effective release onward.

Stable behavioral claims receive contract IDs that link normative documentation to designated
compatibility fixtures. Those fixtures prove the promise but do not independently create a new
promise. Ordinary unit tests, implementation details, and undocumented incidental behavior such as
ordering or timing are not automatically public contracts.

When published documentation, accepted decisions, fixtures, and runtime behavior contradict or
leave a promise ambiguous, compatibility classification fails for human resolution. Tooling may
not silently choose either code or prose as authoritative.

## Consequences

Compatibility certification must evaluate structural API changes and behavioral contracts through
separate evidence. A signature-preserving behavioral break can therefore require a higher SemVer
increment.

Fight Common can still correct incidental implementation behavior without permanently supporting
every observable detail. New stable promises remain discoverable because their documentation and
evidence share an explicit contract ID.

## Rejected Alternatives

Treating signatures as the complete compatibility contract was rejected because return values,
ordering, mutation, side effects, and error behavior can break consumers without changing a PHP
declaration.

Treating every test or observable runtime detail as binding was rejected because implementation
tests often encode mechanics rather than consumer promises and would prevent safe maintenance.
