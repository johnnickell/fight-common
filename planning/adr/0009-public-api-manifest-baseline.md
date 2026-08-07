# ADR 0009: Public API Manifest Baseline

- Status: accepted
- Date: 2026-08-06

## Decision

Fight Common will publish a machine-readable public API manifest as the authority for which PHP
declarations carry a versioned compatibility promise.

The initial manifest is seeded from the authoritative published `1.1.0` source at `fdd4806`. Every
production-autoloaded declaration at that baseline is included unless the declaration was already
marked `@internal`. This preserves the compatibility expectations that consumers could reasonably
have formed before an explicit manifest existed.

Every production-autoloaded declaration added after that baseline must be deliberately classified.
Certification fails when a new declaration is neither added to the public manifest nor explicitly
classified as internal.

The manifest records consumer-operation promises independently:

- callable declarations may be invoked through their public methods or function contracts;
- constructible types may be instantiated through their documented constructors or factories;
- extensible types may be subclassed, and their designated protected hooks are stable;
- implementable interfaces may be implemented by consumers.

A declaration being callable or constructible does not make it extensible. The absence of `final`
is a PHP capability, not by itself a Fight Common compatibility promise. A grandfathered type is
classified as extensible only when affirmative evidence shows that intent:

- it is an abstract base designed for consumer specialization;
- it is an exception type intended to support consumer subtypes;
- documentation or tests present it as an extension point;
- another published API type already subclasses it; or
- repository history shows that `final` was deliberately removed to permit consumer extension.

Protected members are stable only for types classified as extensible. Ambiguous grandfathered
types require a repository-history and known-consumer audit before classification. The exact
manifest syntax remains a separate WF-002 decision.

## Consequences

The manifest gives compatibility tooling a deterministic surface instead of asking it to infer
project intent from visibility alone. It also prevents a later manifest from silently narrowing the
existing `1.1.0` contract.

Compatibility checks can distinguish an ordinary callable API change from a change that breaks
consumer subclasses or implementations. Public visibility alone does not silently promise every
possible form of reuse. Fight Common may leave a class non-final for framework, proxy, testing, or
implementation reasons without committing to third-party inheritance. The evidence supporting
each extensible classification remains reviewable alongside the manifest.

Fight Common accepts that grandfathering the published surface may make some future changes require
a major release. New implementation details can remain outside the public contract, but their
classification must be intentional and reviewable before release.

## Rejected Alternatives

Treating every non-`@internal` declaration as permanently public was rejected because a missed
annotation would silently create a lasting compatibility promise.

Starting the manifest as an opt-in list for `1.2.0` was rejected because it could retroactively
remove declarations that `1.1.0` consumers already use.
