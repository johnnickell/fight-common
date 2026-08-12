# ADR 0004: Versioned Coding-Standard Compatibility Contract

- Status: accepted
- Date: 2026-08-01

## Decision

The canonical `FightCommon` ruleset, its custom sniff identifiers and diagnostic codes, and its configurable
properties are public versioned contracts. Consumers must be able to update within an allowed Composer
version range without an unannounced change making previously compliant code fail the default ruleset.

Patch releases may fix false positives, documentation, compatibility, or performance when the fix does not
introduce a new default violation for previously compliant code. They may not enable a rule, tighten accepted
syntax, rename or remove a sniff or diagnostic code, or incompatibly change a configurable property.

Minor releases may add new opt-in sniffs, diagnostic capabilities, or backward-compatible configuration
properties. New sniffs remain disabled in the canonical ruleset until a major release. A minor release may
relax a rule when the change preserves the meaning of the standard and is documented.

A major release is required to enable a new default rule, make an existing rule report additional previously
compliant code, remove or rename a sniff or diagnostic code, remove or incompatibly change a property, or
otherwise tighten the canonical ruleset. Major release notes provide the migration guidance required to
restore compliance.

Focused compatibility tests cover the stable standard name, custom sniff identifiers, diagnostic codes, and
documented property names. Consumer examples reference those public identifiers rather than implementation
class paths where PHPCS supports the stable identifier.

T-00046 establishes the initial custom identifiers under `Phpcs.*` before the standard's first release because
PHP_CodeSniffer derives that prefix from the flat `Fight\Common\Standards\Phpcs\Sniffs` implementation namespace.
The compatibility policy applies to those identifiers beginning with the first release that contains the standard;
the unreleased development names require no alias or deprecation path.

## Consequences

Fight Common may improve correctness in patch and minor releases when doing so removes false positives or
preserves existing compliance, but stricter defaults accumulate for the next major release. Consumers receive
predictable dependency updates instead of surprise CI failures.

A defect that currently permits code contrary to the documented standard cannot be silently corrected in a
patch or minor release when that correction would create new failures. The defect is documented, an opt-in
fix may be added compatibly, and default enforcement waits for the next major release.

## Rejected Alternative

Allowing any bug fix or newly adopted convention to tighten the default ruleset in patch or minor releases
was rejected. Although that would evolve enforcement faster, it would make routine dependency updates break
consumer builds and would treat the published standard as internal implementation rather than a reusable
contract.
