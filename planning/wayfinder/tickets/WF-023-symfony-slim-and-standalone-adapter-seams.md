# Research Symfony, Slim, and standalone adapter seams

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the service-container and framework-adapter namespace model](WF-019-service-container-and-adapter-namespace-model.md)

## Question

Which existing Symfony adapters should be relocated, generalized, or reused by Slim, and which standalone
provider integrations should become first-class Fight Common adapters across frameworks?

## Research scope

Audit the live source and current primary documentation. Cover Symfony service-container compiler passes,
Messenger buses and serializer, neutral message handlers, HTTP responses, controllers, middleware,
Doctrine persistence and UnitOfWork, filesystem, routing, mail, process, cache, observability, Mercure,
templating, and Slim's PSR/container composition. Evaluate standalone providers including Monolog and other
already-installed or well-maintained libraries; treat Bernard as a research candidate rather than a presumed
supported default.

Record findings in
`planning/wayfinder/research/WF-023-symfony-slim-and-standalone-adapter-seams-research.md`.

## Resolution

The [research note](../research/WF-023-symfony-slim-and-standalone-adapter-seams-research.md) classifies the
existing Symfony, provider, and neutral adapter surfaces. It selects service-container relocation for compiler
passes, neutral canonical names for the two message handlers, a PSR HTTP lane for Slim, provider-owned Doctrine
persistence, direct PSR-3 use for Monolog, and rejection of Bernard from the supported matrix.
