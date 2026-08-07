# WF-002 supported lines and compatibility research

**Research date:** 2026-08-06

**Scope:** Primary-source findings for
[`WF-002`](../tickets/WF-002-supported-lines-and-compatibility-contract.md). This note records evidence
and recommendations to consider; it does not approve a compatibility policy, support transition,
tool installation, dependency change, or release.

## Executive findings

- SemVer cannot classify Fight Common releases until the project precisely declares its public API.
  SemVer defines patch as a backward-compatible fix for incorrect behavior, minor as backward-compatible
  public functionality or a deprecation, and major as any backward-incompatible public-API change.
  It also warns that restoring intended behavior can merit a major release when users substantially rely
  on the defect. [SemVer 2.0.0](https://semver.org/)
- Composer constraints are executable sets, not prose. Caret constraints follow the next-significant-release
  model (with special handling below `1.0`), and `composer/semver` exposes parsing, satisfaction,
  intersection, and subset operations. Those operations can mechanically identify constraint widening,
  narrowing, and disjointness, but they do not decide whether the resulting runtime behavior is compatible.
  [Composer versions and constraints](https://getcomposer.org/doc/articles/versions.md),
  [`composer/semver`](https://github.com/composer/semver)
- Roave/BackwardCompatibilityCheck is a strong structural PHP API comparator, but not a complete
  compatibility oracle. It compares committed Git revisions, discovers code through Composer autoloading,
  returns non-zero for detected API breaks, and can use regex ignores. Its documented default discovers the
  last minor SemVer tag, so explicit baseline refs are safer for parallel maintenance lines and this
  repository's mixed `v1.1.0`/`1.1.0` tag history.
  [Roave Backward Compatibility Check](https://github.com/Roave/BackwardCompatibilityCheck)
- PHPStan's general analysis improves type, exception, and deprecation discipline, but its documented
  `phpstanApi.*` compatibility checks protect PHPStan's own extension API, not an arbitrary library's
  historical API. Its deprecation checking requires the separate first-party deprecation-rules extension.
  PHPStan therefore complements, rather than replaces, a revision-to-revision API comparator and behavioral
  tests. [PHPStan BC promise](https://phpstan.org/developing-extensions/backward-compatibility-promise),
  [`phpstan-deprecation-rules`](https://github.com/phpstan/phpstan-deprecation-rules)
- Symfony offers useful principles: no BC breaks within a major line, staged deprecations, security-only BC
  exceptions, and fixing a bug on the oldest maintained branch that contains it. Its release calendar,
  LTS durations, broad extension promises, and project-scale governance are precedents, not defaults for
  Fight Common. [Symfony BC promise](https://symfony.com/doc/current/contributing/code/bc.html),
  [release process](https://symfony.com/doc/current/contributing/code/releases.html),
  [maintained releases](https://symfony.com/releases)
- A structural API check alone cannot prove compatibility of documented behavior, exception outcomes,
  serialized representations, durable storage, framework integration, or coding-standard diagnostics.
  Those require category-specific executable fixtures and comparisons.

## Repository evidence that constrains the policy

- Production autoload exposes `Fight\Common\` from `src/` and loads `src/Domain/functions.php` directly.
  The repository has six `@internal` occurrences across five files; only one is class-level and several mark
  helper methods. Therefore a rule such as "all public/protected code under production autoload is public
  unless explicitly excluded" would be much broader than a rule based on deliberate `@api` declarations.
  Choosing between those models is a consequential policy decision, not a tooling detail.
- Accepted [ADR 0004](../../adr/0004-coding-standard-compatibility.md) defines the planned `FightCommon`
  PHPCS ruleset, custom sniff identifiers, diagnostic codes, configurable properties, and default enforcement
  as public versioned contracts. `src/Standards` does not yet exist on this branch, so this is a future
  contract that will need category-specific compatibility checks once implemented, even if its PHP class
  signatures remain unchanged.
- `composer.json` currently requires PHP `>=8.5`, uses caret ranges for runtime PSR packages, and places
  framework/adapter dependencies in `require-dev` plus `suggest`. Its `config.platform` pins PHP and
  extensions to `8.5.4` for dependency resolution. A compatibility policy must distinguish the published
  consumer requirement (`require.php`) from the repository's simulated resolution platform
  (`config.platform`).
- Current release-line evidence includes branches `1.0` and `1.1`, lightweight tags `v1.0.0` and `v1.1.0`,
  and an annotated bare `1.1.0` tag. The annotated tag object is `5f1c2f2`, peels to `fdd4806`, and therefore
  does **not** identify the `be965a0` commit named by `v1.1.0`. Roave's automatic baseline requires `x.y.z`
  tags and therefore should not be the authority for line selection.
- [WF-001](../tickets/WF-001-release-destination-and-boundaries.md) has already established the initial
  support shape: current minor normal fixes; immediately previous minor limited security, data-loss, and
  critical-compatibility fixes for six months; only the latest patch per supported minor; oldest affected
  supported line first, then explicit forward ports. WF-002 still must make that shape unambiguous and
  machine-checkable.

## SemVer and Composer behavior

### Release recommendation floor

SemVer requires a declared public API and classifies by effect on that API:

| Observed change | SemVer floor | Important qualification |
| --- | --- | --- |
| Internal fix for incorrect behavior, backward compatible | Patch | A widely relied-on defect can make the nominal fix disruptive enough to justify major. |
| Backward-compatible public functionality | Minor | May include patch changes. |
| Mark public functionality deprecated | Minor | Removal waits for a later major. |
| Any backward-incompatible public-API change | Major | Size of the break does not lower the required class. |

Source: [Semantic Versioning 2.0.0, rules 1 and 5-8 plus FAQ](https://semver.org/).

A deterministic command can recommend the **minimum** version bump established by evidence. It cannot know
all undocumented consumer reliance, nor should it authorize a version. A safe authorization contract would
bind human approval to the exact proposed version, immutable candidate commit, comparison baseline(s),
supported lines affected, machine findings, behavioral evidence, and any explicitly requested exception.
Approval of a bump class alone should not authorize a different version or candidate.

### Constraint set comparisons

Composer normalizes tags, branches, stabilities, and constraints according to Composer's own rules. Notable
behaviors include:

- `^1.2.3` accepts `>=1.2.3 <2.0.0`, while `^0.3` accepts `>=0.3.0 <0.4.0`;
- AND binds more tightly than OR in compound constraints;
- dev, alpha, beta, RC, and stable candidates are filtered by stability policy;
- the solver selects a version satisfying all package constraints, not merely the highest version allowed by
  Fight Common's direct constraint.

Source: [Composer versions and constraints](https://getcomposer.org/doc/articles/versions.md).

For each `require`, `conflict`, `provide`, `replace`, and PHP/extension platform entry, a future checker could
parse baseline and candidate constraints using `Composer\Semver\VersionParser` and compare their sets with
`Intervals::isSubsetOf()` and `Intervals::haveIntersections()`. This supports objective findings:

- candidate is a strict subset of baseline: accepted versions were removed (narrowing);
- baseline is a strict subset of candidate: new versions were admitted (widening);
- neither is a subset: the permitted set shifted;
- no intersection: the contract was replaced.

Source: [`composer/semver` API](https://github.com/composer/semver).

The set relation is evidence, not the release verdict. Raising the minimum PHP version or narrowing a runtime
dependency range excludes previously valid consumers and is normally a major-level finding. Widening can be
source-compatible yet still needs lowest/latest resolution evidence because it admits combinations never
previously promised. Runtime requirements, dev-only build requirements, suggestions, and `config.platform`
need separate classifications rather than a single undifferentiated Composer diff.

Composer's official CLI provides useful supporting gates: `validate --strict` checks publishability and lock
consistency; `update --prefer-lowest --prefer-stable` exercises minimum permitted dependencies;
ordinary update exercises latest permitted dependencies; `prohibits` explains blockers; and
`check-platform-reqs` checks the real runtime rather than `config.platform`.
[Composer CLI](https://getcomposer.org/doc/03-cli.md)

## PHP API compatibility tooling

### Roave/BackwardCompatibilityCheck

Documented strengths:

- compares two versions of a PHP library's class API;
- expects Git, Composer metadata, and complete production autoload coverage;
- requires changes to be committed unless custom source/dependency extraction is supplied;
- exits non-zero when it detects BC breaks and supports CI/GitHub output;
- supports regex-based baseline ignores.

Source: [Roave README](https://github.com/Roave/BackwardCompatibilityCheck/blob/8.22.x/README.md).

Limitations relevant to WF-002:

- automatic "last minor" tag discovery is not sufficient when certifying an older maintenance line;
- shallow clones without tags break automatic discovery;
- a revision must be parseable and dependency-resolvable under the tool's runtime;
- it compares structural class API, not documented outputs, side effects, exception messages, serialized bytes,
  database schemas, framework service wiring, or PHPCS diagnostics;
- regex ignores are suppression, not proof that a break is safe, and can become stale or over-broad.

Recommendations to consider: pass explicit immutable baseline and candidate refs selected by repository-owned
line logic; retain full raw findings as evidence; require each ignore to carry a stable ID, exact finding,
rationale, expiry/review condition, and separate human approval. Never infer an approved patch exception merely
because a regex is present.

### PHPStan and deprecations

PHPStan levels check calls, argument and return types, PHPDoc, and increasingly strict type behavior. It also
has exception-analysis options, including checking missing `@throws` declarations and throw-type covariance.
[PHPStan rule levels](https://phpstan.org/user-guide/rule-levels),
[configuration reference](https://phpstan.org/config-reference)

PHPStan's `@api`-based rules are a valuable **policy precedent**: they distinguish safe-to-call,
safe-to-construct, safe-to-extend, and safe-to-implement surfaces. But the documented `phpstanApi.*` rules
enforce the BC promise for code extending PHPStan itself. They do not compare two Fight Common revisions.
[PHPStan BC promise](https://phpstan.org/developing-extensions/backward-compatibility-promise)

The first-party `phpstan/phpstan-deprecation-rules` extension reports consumers' use of declarations marked
`@deprecated`; core PHPStan supports custom deprecation metadata. Neither capability establishes that a
deprecation was introduced in the correct minor release or that removal waited for a major release.
[`phpstan-deprecation-rules`](https://github.com/phpstan/phpstan-deprecation-rules),
[custom deprecations](https://phpstan.org/developing-extensions/custom-deprecations)

Recommendation to consider: use PHPStan for current-tree correctness and exception/deprecation discipline,
Roave for historical structural API comparison, and repository-owned fixtures for the remaining contract.
Do not install either new capability during WF-002 research.

## Compatibility categories that need explicit treatment

| Category | Candidate public contract | Machine-checkable evidence to consider | Gap left by API diff |
| --- | --- | --- | --- |
| PHP declarations | Declared API classes, interfaces, traits, enums, functions, constants; callable/constructible/extendable/implementable status; public and promised protected members | Explicit-ref Roave comparison plus an API inventory | Cannot prove runtime semantics or consumer reliance outside declared surface |
| Documented behavior | Return values, ordering, mutation, side effects, null/empty/error cases, idempotence, timing/precision guarantees | Baseline behavioral fixtures run against baseline and candidate; documentation-contract tests | Static signatures can remain identical while behavior breaks |
| Exceptions | Documented throwable type, code, causal chain, and any explicitly stable message/diagnostic fields | Public tests for exact promised outcomes; PHPStan `@throws` consistency | Static analysis does not prove runtime branch coverage; prose messages should not become stable accidentally |
| Serialization | JSON/PHP representations, field names/types, omission/default rules, round trips, stable event names and schema versions | Golden fixtures and backward-read tests; baseline data decoded by candidate | Reflection cannot see wire/storage shape |
| Database adapters | SQL/schema shape, type names, conversions, transaction/concurrency guarantees, existing persisted data | Per-supported DB behavioral/schema fixtures and migration-free backward-read tests where promised | Doctrine-compatible signatures can still emit incompatible SQL or storage |
| Framework adapters | Implemented framework interfaces, service IDs/tags, events, config, compiler-pass behavior, supported dependency ranges | Lowest/locked/latest dependency matrices plus container/integration fixtures | Upstream API compatibility does not prove wiring or behavior |
| Coding standard | `FightCommon` ruleset, sniff IDs, diagnostic codes, configurable properties, default enabled rules and accepted code | Fixture corpus comparing baseline and candidate diagnostics; ADR 0004 compatibility assertions | PHP class API says nothing about new CI failures |
| Composer/package surface | Package name, production autoload/files, runtime/platform constraints, replace/provide/conflict metadata, archive contents | Strict schema validation, constraint set diff, clean exported-package installs at lowest/locked/latest resolutions | Successful solving does not prove runtime compatibility |

Recommendation to consider: maintain a machine-readable API manifest that explicitly marks each symbol's
allowed consumer operations, following PHPStan's useful distinction between call, construct, extend, and
implement. If Fight Common instead treats every non-`@internal` autoloaded symbol as public, state that broad
promise plainly and accept the corresponding major-version pressure.

## Symfony principles: adopt selectively

Symfony promises BC within a major line, excludes experimental and `@internal` code, permits security-driven
exceptions, stages removals through deprecations, and documents granular interface/class/trait compatibility.
Its maintained-release page says bugs are fixed in the oldest maintained branch containing them. These are
directly relevant principles for a shared PHP library.
[Symfony BC promise](https://symfony.com/doc/current/contributing/code/bc.html),
[Symfony releases](https://symfony.com/releases)

Principles worth considering for Fight Common:

- define public and internal surfaces explicitly;
- make patch upgrades behaviorally safe, with narrowly reviewed security exceptions;
- introduce deprecations compatibly before major removal;
- repair the oldest affected maintained line first and move the fix forward;
- publish exact support/EOL state in a machine-consumable form.

Principles not justified merely by precedent:

- Symfony's six-month feature cadence, two-year majors, five minors, and multi-year LTS windows;
- its promise that essentially all non-internal classes can be extended and all interfaces implemented;
- its policy of supporting future PHP majors throughout a maintained line;
- its organizational roles and volume-driven backport process.

Fight Common has a smaller maintainer and consumer surface and has already chosen a six-month previous-minor
window. Copying Symfony's calendar or expansive inheritance promise would add obligations without evidence
that they fit this package.

## Machine-readable support policy precedents

Symfony publishes its release lifecycle as JSON at
[`https://symfony.com/releases.json`](https://symfony.com/releases.json), alongside the human release page.
The Node.js Release Working Group keeps a versioned
[`schedule.json`](https://github.com/nodejs/Release/blob/main/schedule.json) with release-line keys and explicit
start, LTS, maintenance, and end dates. Node's README derives human status from the schedule and documents the
meaning of its phases. These demonstrate two useful properties: lifecycle dates can be version-controlled or
published as data, and display status can be derived rather than manually duplicated.

Recommendations to consider for `SUPPORTED_VERSIONS.md`:

- one canonical, strictly parsed YAML or JSON data block with `schema_version`;
- one record per `major.minor` line containing branch, initial release tag/commit, latest supported tag/commit,
  support phase, normal-fix cutoff, limited-fix cutoff/EOL, allowed fix classes, and superseding line;
- ISO `YYYY-MM-DD` dates interpreted against an explicitly named clock and boundary rule (prefer UTC and an
  exclusive `ends_at` instant if exact automation matters);
- state derived from dates plus explicit exceptional overrides, with validation rejecting contradictory or
  missing transitions;
- EOL lines retained as immutable historical records and treated read-only by release tooling;
- the human table rendered from, or verified against, the machine block so two authorities cannot drift.

The policy still must decide whether transition dates are inclusive local dates or exact instants, what happens
to an in-flight approved patch at EOL, and whether an emergency support extension requires a separately reviewed
policy commit before any release work.

## Baselines and affected-line proof

### Baselines by release class

Recommendations to consider:

- **Patch:** compare against the latest released patch tag on that exact maintenance line. This is the contract
  a supported user upgrades from.
- **Minor:** compare against the latest release of the preceding minor within the same major; allow additions
  and deprecations but block breaks.
- **Major:** compare against the latest release of the preceding supported major to produce a complete migration
  inventory; findings inform and document the authorized major rather than automatically blocking it.
- Resolve every baseline tag to its peeled commit and record both tag identity and commit OID. Reject ambiguous,
  missing, moving, or non-ancestor refs instead of guessing.

This repository's conflicting `v1.1.0` and `1.1.0` history is a concrete reason to select baselines through
validated release metadata rather than lexical "latest tag" rules.

### Affected lines and oldest correct base

Git provides deterministic ancestry primitives: `git merge-base --is-ancestor A B` returns success only when
`A` is an ancestor of `B`, and `git branch --contains <commit>` lists branches whose tips descend from that
commit. [Git `merge-base`](https://git-scm.com/docs/git-merge-base),
[Git `branch --contains`](https://git-scm.com/docs/git-branch.html)

Ancestry alone proves only that code history is present, not that a defect is observable. A robust affected-line
declaration would include:

1. the supported-line IDs claimed affected and unaffected;
2. immutable latest-patch baseline OIDs for each line;
3. the defect-introducing or earliest-known-affected commit when known, plus ancestry results;
4. one focused public behavioral regression test or fixture that fails on every claimed affected baseline and
   passes on every claimed unaffected baseline where the test is applicable;
5. the proposed fix commit, showing the same test passes on the oldest affected supported base;
6. separate forward-port commits and complete certification evidence for every newer affected line.

If the exact introducing commit is unknown, tooling should report that fact rather than fabricate precision.
The failing baseline fixture plus supported-line declaration can still prove release impact. The "oldest correct
base" is the oldest **supported and demonstrably affected** line, not simply the oldest branch containing a
suspect commit.

## Patch exceptions and human control

SemVer and Symfony both leave a narrow real-world exception: security or defect correction can conflict with
strict compatibility, and restoring intended behavior may itself seriously disrupt users. An exception should
therefore be explicit evidence, never a silent tool suppression.

Recommendations to consider for an exception record:

- exact candidate commit, release version, line, and baseline;
- stable IDs and raw findings being overridden;
- category (`security`, `data-loss`, or approved critical compatibility), severity, exploit/data impact, and why
  no compatible repair exists;
- known consumer impact, migration/mitigation, tests, and rollback/recovery posture;
- approving human identity and timestamp;
- narrow scope and expiry, with no wildcard approval for future findings;
- forced minimum SemVer recommendation of major unless the human explicitly authorizes the policy exception for
  the named patch release.

Tooling may calculate and display this recommendation. It must fail closed when evidence or exact authorization
does not match the candidate.

## Open decisions for grilling

Research narrows, but does not answer, these decisions:

1. Opt-in API manifest versus broad non-`@internal` autoload surface.
2. Whether protected members and subclassing are promised for every non-final class or only designated types.
3. Exact stability of exception messages, serializer bytes versus semantic structures, and database schemas.
4. Exact `SUPPORTED_VERSIONS.md` data syntax, clock boundary, and exceptional extension process.
5. Exact patch-exception approver and whether any non-security incompatibility is permitted.
6. Which baseline tool composition to adopt after a later implementation authorizes dependency/tool changes.
7. Whether a fix can proceed when the introduction commit is unknown but cross-line behavioral evidence is
   complete.

## Primary sources

- [Semantic Versioning 2.0.0](https://semver.org/)
- [Composer versions and constraints](https://getcomposer.org/doc/articles/versions.md)
- [Composer CLI](https://getcomposer.org/doc/03-cli.md)
- [`composer/semver`](https://github.com/composer/semver)
- [Roave Backward Compatibility Check](https://github.com/Roave/BackwardCompatibilityCheck/blob/8.22.x/README.md)
- [PHPStan backward compatibility promise](https://phpstan.org/developing-extensions/backward-compatibility-promise)
- [PHPStan configuration reference](https://phpstan.org/config-reference)
- [`phpstan/phpstan-deprecation-rules`](https://github.com/phpstan/phpstan-deprecation-rules)
- [Symfony backward compatibility promise](https://symfony.com/doc/current/contributing/code/bc.html)
- [Symfony release process](https://symfony.com/doc/current/contributing/code/releases.html)
- [Symfony maintained releases and JSON feed](https://symfony.com/releases)
- [Node.js Release Working Group schedule](https://github.com/nodejs/Release/blob/main/schedule.json)
- [Git `merge-base`](https://git-scm.com/docs/git-merge-base)
- [Git `branch`](https://git-scm.com/docs/git-branch.html)
