# Define the service-container and framework-adapter namespace model

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Audit Fight Common contracts and the 1.2 compatibility envelope](WF-014-fight-common-contract-and-compatibility-audit.md)

## Question

Where should portable primitives, framework runtime adapters, and equivalent service-container extension
points live while Fight Common expands beyond Symfony?

## Resolution

- Use `Adapter\ServiceContainer\<Framework>\<Type>` for Symfony compiler passes, Laravel service providers,
  Yii configuration providers, and CodeIgniter service factories.
- Keep each provider or compiler pass bounded to one capability so consumers do not wire unrelated optional
  integrations.
- Keep concrete runtime integrations under `Adapter\<Capability>\<Framework-or-Provider>\<Type>`.
- Use `Application\Http` for the neutral `HttpMethod` and `HttpStatus` primitives and
  `Application\ServiceContainer\Container` for the portable PSR-11 container.
- Use `Adapter\Http\<Framework>` for native JSend responses,
  `Adapter\Http\<Framework>\Controller` for controllers, `Adapter\Middleware\<Framework>` for middleware,
  and `Adapter\Persistence` for Repository, data-type, and UnitOfWork implementations.
- Publish the corrected paths additively in `1.2.0`, retain old FQCNs throughout `1.x`, and remove them only
  in `2.0.0`. Defer only a capability that proves it needs an inward breaking change.

The decision is recorded in [ADR 0023](../../adr/0023-service-container-and-framework-adapter-namespaces.md).
It supersedes the conflicting namespace destinations in ADR 0019 and the blanket no-new-shared-adapter policy
in ADR 0021.
