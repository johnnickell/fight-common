# ADR 0005: Layer-Specific Dependency Allowance Matrix

- Status: accepted
- Date: 2026-08-01

## Decision

Deptrac enforces every production and Standards class through an explicit layer with no baseline, skipped
violation, or unassigned project namespace. Runtime dependencies continue to point inward:
`Adapter -> Application -> Domain`. Standards remain an orthogonal development-time layer.

Domain may depend only on Domain and PHP internals. It cannot depend on Application, Adapter, Standards,
PSRs, or other third-party packages.

Application may depend on Application, Domain, PHP internals, and neutral PSR contracts used as portable
boundaries. `Cron\CronExpression` is the single concrete utility exception: it parses and evaluates cron
expressions without imposing a framework or infrastructure implementation. The allowance is exact rather
than a general permission for the `Cron` namespace or arbitrary utilities.

Application cannot depend on Symfony Process. `Scheduler` must execute shell commands through Fight Common's
existing Application-owned `ProcessRunner` port and its Adapter implementation. The architecture migration
preserves Scheduler's public behavior while removing direct construction of the concrete framework process.

Adapter may depend on Adapter, Application, Domain, PHP internals, PSRs, and the explicitly configured
infrastructure packages its implementations integrate. External allowances use bounded namespace collectors
rather than one unrestricted third-party bucket, so dependency expansion remains visible in review.

Adapter is intentionally flexible: it may implement an inward-owned port, implement a framework extension
point, translate between external and Fight Common types, or package third-party behavior behind a convenient
Fight Common API. Port implementation is the common shape, not a requirement for membership in Adapter.
Development policy remains outside this runtime meaning, so Standards do not become Adapters merely because
they extend PHP_CodeSniffer or Slevomat.

Standards may depend only on Standards, PHP internals, PHP_CodeSniffer, and Slevomat. Domain, Application,
and Adapter cannot depend on Standards, and Standards cannot depend on a runtime Fight Common layer.

## Consequences

Adding a new external dependency to Domain, Application, or Standards requires an explicit architectural
decision rather than silently widening a generic allowlist. Adapter additions require a deliberate bounded
collector and ruleset entry that names the integrated infrastructure package.

Neutral PSR contracts remain valid Application boundaries. Concrete framework behavior remains in Adapter,
except for the narrow `CronExpression` utility whose replacement by an owned port would add indirection
without changing an infrastructure boundary.

## Rejected Alternatives

Allowing every Composer dependency from Application was rejected because it would make the inward boundary
nominal while concrete framework implementations leaked into coordination code.

Forcing `CronExpression` behind a Fight Common port was rejected because it is a deterministic expression
utility rather than infrastructure, and wrapping its small stable behavior would add ceremony without
protecting a meaningful boundary.

Allowing Symfony Process in Scheduler was rejected because an owned `ProcessRunner` port and Symfony adapter
already exist. The direct dependency bypasses that established seam.

One generic third-party Deptrac layer was rejected because it would allow future external dependencies to
enter a layer without an explicit reviewable configuration change.
