# ADR 0011: Non-Structural Compatibility Policy

- Status: accepted
- Date: 2026-08-06

## Decision

Fight Common applies the following versioned compatibility rules beyond PHP declaration shape.

### Exceptions

Documented throwable families, catchability, integer codes, structured diagnostic fields, and
documented causal chains are behavioral contracts. Prose exception messages are unstable unless an
explicit behavioral contract ID says otherwise. Introducing an exception on a previously
successful documented path is incompatible.

### Serialization and persisted data

Serialization promises semantic structure by default: field names, types, required or optional
status, omission and default rules, stable aliases, schema versions, round trips, and backward
reads. JSON whitespace and object-key order and PHP serialized bytes are not stable unless an
explicit contract requires byte identity.

Patch and minor upgrades must continue reading existing supported data without destructive or
mandatory data rewrites. A minor may add optional schema for a new capability, but existing
consumers remain operable without adopting it. Existing database type names, conversions, and
documented transaction guarantees remain stable.

### Frameworks, platforms, and dependencies

Patch and minor releases may not remove a previously accepted PHP version, extension, framework,
dependency version, or other supported runtime environment. Widening support requires passing the
lowest-permitted, repository-locked, and latest-permitted dependency lanes. A new optional
integration may be minor. A new required dependency is at least minor and requires a major release
when it excludes a previously valid installation.

Public framework service IDs, configuration, tags, events, and adapter behavior are versioned
contracts.

### Deprecations

New deprecations are introduced only in minor releases. The deprecated contract remains fully
functional for at least one released minor before removal in a later major release. Patch releases
may repair deprecation metadata but may not newly deprecate supported behavior. Runtime deprecation
warnings are behavioral changes and require the same compatibility review as other observable
behavior.

## Consequences

A source-compatible change may still require a higher SemVer increment because it changes errors,
wire or storage meaning, installation eligibility, framework wiring, or runtime warnings.

Fight Common must retain backward-read fixtures, dependency matrices, and designated adapter
fixtures where those contracts apply. Exact bytes, prose diagnostics, and incidental framework
mechanics remain changeable unless explicitly promised.

## Rejected Alternatives

Treating source compatibility as sufficient was rejected because consumers also depend on stored
data, dependency solving, framework wiring, and documented runtime outcomes.

Allowing supported environment floors to rise in a minor release was rejected because consumers
within the declared range could no longer install an otherwise compatible update.
