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

The inspected maximum remains the `minimum_release_class`; it does not describe a higher exact
version that a human authorizes. The authoritative plan separately derives `release_class` from the
approved version relative to the bound baseline, so a patch minimum with an approved next major is
recorded as minimum `patch` and actual `major`. Both classes are bound by the typed release approval.

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

Inspection accepts one category record for each independently composed policy surface: `structural-api`,
`compatibility-manifest`, `composer-constraints`, `package-surface`, `archive-contents`,
`behavioral-fixtures`, `serialization-fixtures`, `persistence-fixtures`, `adapter-fixtures`,
`dependency-lowest`, `dependency-locked`, `dependency-latest`, `static-analysis`, and
`deprecation-discipline`. Each record contains exactly `category`, category-scoped stable `finding_id` and
`evidence_id` values, and a `classification` of `patch`, `minor`, `major`, or `indeterminate`. The complete set
must be unique and is canonicalized into that order. The minimum recommendation is the maximum of the
independent `patch < minor < major` classifications; an indeterminate, missing, duplicate, unknown, malformed,
or caller-declared aggregate blocks recommendation before Git inspection. A legacy `change_class` is not
release evidence and is rejected.

Roave Backward Compatibility Check, `composer/semver`, PHPStan, and PHPUnit may produce evidence;
repository policy classifies the release. Tool findings use stable IDs so exact exceptions and
human authorization can bind to them.

A lower-patch exception binds the complete canonical category assessment and must override exactly
its non-patch category finding IDs. Authority-shaped identifiers that do not resolve to that bound
assessment, or a stale assessment/content identity, cannot authorize the lower release class.

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
