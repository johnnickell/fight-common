---
template: atlas-article.html
atlas_article: true
title: Observability
atlas_article_heading_id: observability
atlas_component_group: Operate Workloads
atlas_component_owner: Domain, Application, and Adapter
atlas_component_dependencies: PSR-3, optional Doctrine DBAL, HTTP client, and ext-sockets
atlas_article_context: Domain · Application · Adapter
atlas_article_lead: Report health, emit metrics, and record audit facts through portable contracts while keeping providers and disclosure policy at the boundary.
atlas_article_requires: PHP 8.5+, psr/log
atlas_article_optional: doctrine/dbal, psr/http-message, ext-sockets
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Database, HTTP, StatsD, or PSR-3 provider
atlas_relationship_target_label: Application port
atlas_relationship_target: HealthAggregator, MetricsCollector, and AuditLog
atlas_relationship_description: Provider adapters fulfill portable observability contracts
atlas_relationship_caption: Application code chooses what to observe; adapters perform external probes, metric delivery, and audit recording.
atlas_consequential_label: Disclosure boundary
atlas_consequential_message: Health messages, metric tags, and audit context can leave the process; treat every value as operational data and exclude secrets and unnecessary personal information.
atlas_next_steps:
  - label: Aggregate health checks
    href: "#health-checks"
  - label: Emit application metrics
    href: "#metrics"
  - label: Record audit facts
    href: "#audit-log"
  - label: Protect signed requests
    href: "../auth/"
  - label: Trace message handling
    href: "../messaging/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Health checks
    href: "#health-checks"
  - label: Metrics
    href: "#metrics"
  - label: Audit log
    href: "#audit-log"
  - label: Provider selection
    href: "#provider-selection"
  - label: Failure behavior
    href: "#failure-behavior"
  - label: Privacy and operations
    href: "#privacy-and-operations"
---

Observability exposes three distinct seams: health reports describe current dependency state,
metrics describe behavior over time, and audit entries preserve business-relevant facts. Depend on
the Application ports in use cases and select probes, collectors, and sinks in the composition root.

**Ownership.** Health status, results, reports, and audit entries are Domain values. The Application
layer owns `HealthCheck`, `HealthAggregator`, `MetricsCollector`, and `AuditLog`. Database and HTTP
checks, StatsD delivery, and PSR-3 audit sinks are adapters. The consuming application owns endpoint
exposure, alert thresholds, metric cardinality, audit retention, access control, and redaction.

**Dependencies.** The portable contracts require PHP 8.5+ and this package. PSR-3 supports the
logging audit adapter. Install Doctrine DBAL for database probes, an HTTP client/factory adapter for
endpoint probes, or `ext-sockets` for StatsD only when that provider is selected.

**Install.**

```bash
composer require johnnickell/fight-common
```

--8<-- "docs/observability.md"

For request signing, nonce replay protection, password hashing, and token verification, use the
[Authentication guide](../auth/index.md). Those are trust-boundary concerns, not observability providers.

For the privacy implications of logging delivery metadata, see the [Mail guide](../mail/index.md).
