# Define supported release lines and the compatibility contract

**Labels:** `wayfinder:research`, `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** AFK -> HITL
**Status:** Open
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Establish the release-coordination destination and standing boundaries](WF-001-release-destination-and-boundaries.md)

## Question

What exact, machine-checkable policy classifies a change as patch, minor, or major; identifies the
supported release lines affected by a defect; and determines which public API, behavioral,
dependency, PHP-platform, and Composer-constraint changes are permitted on each line?

## Must decide

- the public API surface and comparison baseline for each release class;
- treatment of documented behavior, deprecations, exception behavior, serialization, database and
  framework adapters, and the reusable coding standard;
- machine-readable `SUPPORTED_VERSIONS.md` structure, clocks, support transitions, and EOL behavior;
- how a fix declares affected lines and how tooling proves the oldest correct base;
- permitted patch exceptions, their evidence, and human approval;
- the minimum SemVer recommendation and exact human authorization contract;
- which Symfony maintenance principles fit Fight Common and which do not.

## Resolution boundary

Resolve policy and testable acceptance scenarios. Research existing compatibility tools and current
Composer/SemVer behavior, but do not install a tool, change dependencies, rewrite documentation, or
alter a branch.
