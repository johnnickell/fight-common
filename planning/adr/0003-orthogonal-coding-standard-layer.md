# ADR 0003: Orthogonal Development-Time Coding-Standard Layer

- Status: accepted
- Date: 2026-08-01

## Decision

Fight Common publishes its reusable PHP_CodeSniffer ruleset, custom sniffs, and supporting helpers under
`src/Standards/Phpcs` in the `Fight\Common\Standards\Phpcs` namespace.

Standards are an orthogonal development-time layer rather than part of the runtime
`Adapter -> Application -> Domain` dependency chain. Standards may depend on PHP_CodeSniffer, Slevomat, and
PHP internals required to implement their tooling contracts. Standards do not depend on Domain, Application,
or Adapter code, and those runtime layers do not depend on Standards.

Fight Common's Deptrac configuration assigns every class under `Fight\Common\Standards` to an explicit
Standards layer and enforces that isolation without a baseline or skipped violation. A consuming project's
production dependency graph is unaffected unless its production code explicitly references the Standards
namespace; executing the installed PHPCS ruleset does not itself create a production dependency edge.

Keeping the tooling under `src` preserves the package's normal Composer autoloading, test mirroring, static
analysis, and exact complete-coverage obligations. It also creates one deliberate home for future reusable
development standards without broadening the meaning of Adapter.

Fight Common retains its ordinary Composer library identity. It does not change package type to
`phpcodesniffer-standard` and does not require a Composer installer plugin. Consumers explicitly load the
installed ruleset from their repository-owned PHPCS configuration. This keeps tool execution opt-in and
preserves the consumer's ownership of scanned paths, exclusions, selected sniffs, and property overrides.

The public integration documentation includes a copy-ready consumer ruleset showing complete-standard
loading, consumer-owned scan and exclusion patterns, individual sniff inclusion and exclusion, and explicit
overrides for configurable sniffs. This documented configuration is the supported discovery contract.

T-00018 transfers canonical ownership of the reusable standard from Omphalos's local implementation to
Fight Common after behavior parity is proven. Omphalos's copy is temporary after that point. Replacing it
with the released Fight Common standard is separately planned and delivered in the Omphalos repository and
does not block Fight Common 1.2 acceptance.

## Rejected Alternatives

`src/Adapter/Phpcs` was rejected because the sniffs extend development-tool contracts rather than adapting an
inward-owned Fight Common port. That placement would mix development policy with runtime integrations and
further weaken the project's Adapter language.

A top-level `standards/FightCommon` directory was rejected because it would require special autoloading,
analysis, and coverage configuration while working against the decision to keep maintained PHP source under
`src`.

Treating Standards as another step in the inward runtime chain was rejected. It is a separately constrained
tooling surface, not a runtime layer that Domain, Application, or Adapter code may consume.

Automatic discovery through the PHP_CodeSniffer Composer installer was rejected because it requires the
package type `phpcodesniffer-standard` and consumer authorization of an executable Composer plugin. Those
requirements misrepresent a package that remains a runtime library and add machinery that clear consumer
configuration can replace.

Indefinite co-ownership between Fight Common and Omphalos was rejected because two editable implementations
would allow the supposedly exact standard to drift. Requiring the consumer migration to block Fight Common
1.2 was also rejected because consumer adoption and release ownership belong to the consuming repository.
