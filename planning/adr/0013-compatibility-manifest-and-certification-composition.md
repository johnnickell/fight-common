# ADR 0013: Compatibility Manifest and Certification Composition

- Status: accepted
- Date: 2026-08-06

## Decision

### Release classification

Fight Common applies Semantic Versioning by consumer-visible effect:

- patch releases contain compatible fixes and relaxations, with no new public feature contract or
  deprecation;
- minor releases may add public contracts, opt-in features, compatible relaxations, and
  deprecations without breaking an existing contract;
- major releases cover removals, renames, narrowed environments, incompatible behavior, unreadable
  stored data, stricter coding-standard defaults, and every other broken contract.

A human may always authorize a higher release class. A lower class requires the exact compatibility
exception defined by ADR 0012.

### Compatibility manifest

The committed `compatibility/manifest.json` is the intentional compatibility authority. It contains
a schema version, authoritative baseline, public declarations and their independent consumer
operation promises, behavioral contract IDs with normative documentation and fixture references,
package-surface promises, and classification evidence.

Source scanning produces inventories that validate manifest completeness. Generated output does not
silently decide which declarations or operations Fight Common promises.

### Composed certification evidence

No single third-party checker is authoritative. A repository-owned command composes:

- explicit-baseline structural API comparison;
- compatibility-manifest validation and diffing;
- Composer constraint-set comparison;
- designated behavioral, serialization, persistence, and adapter fixtures;
- lowest-permitted, repository-locked, and latest-permitted dependency lanes; and
- current-tree static analysis and deprecation discipline.

Roave Backward Compatibility Check, `composer/semver`, PHPStan, and PHPUnit may produce evidence;
repository policy classifies the release. Tool findings use stable IDs so exact exceptions and
human authorization can bind to them.

An indeterminate finding blocks certification. It must be resolved through clarified normative
documentation, a fixture, manifest classification, or an exact approved compatibility exception.

### Composer package surface

The versioned package surface includes the package name, production autoload mappings and files,
PHP and extension requirements, dependency constraints, `conflict`, `provide`, and `replace`
metadata, Composer plugin metadata, and exported archive contents.

Removing or narrowing a package promise requires a major release. Intentional additive runtime
surface is minor. Harmless descriptive metadata may be patch. Unexpected archive drift always
fails certification.

## Consequences

Release classification remains deterministic without delegating policy to one tool's coverage or
defaults. Structural, behavioral, dependency, and package evidence can evolve independently behind
one stable repository command.

Maintainers must classify new production declarations and contracts intentionally. Unknown or
unsupported checker output cannot be interpreted as compatibility merely to complete a release.

## Rejected Alternatives

Using a generated API snapshot as the public-contract authority was rejected because generation
cannot infer project intent. Using Roave or any other single checker as the release authority was
rejected because no one tool covers behavior, serialization, databases, framework wiring,
dependency solving, the coding standard, and archive contents.
