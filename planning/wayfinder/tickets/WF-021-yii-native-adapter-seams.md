# Research Yii-native adapter seams

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the service-container and framework-adapter namespace model](WF-019-service-container-and-adapter-namespace-model.md)

## Question

Which supported Yii facilities can implement or wire each public Fight Common capability as a reusable
optional adapter, and where must Fight Common use a neutral or standalone provider instead?

## Research scope

Use current primary Yii 3 and package documentation. Cover service-container configuration providers,
authentication, cache, persistence transactions and UnitOfWork, filesystem and storage, HTTP client and
PSR-7 responses, middleware, logging and observability, process management, routing, mail, SMS, sockets,
templating, synchronous messaging, queues, serialization, retries, and worker lifecycle. Identify package
stability and version constraints rather than assuming earlier prototype candidates remain current.

Record findings in `planning/wayfinder/research/WF-021-yii-native-adapter-seams-research.md`.

## Resolution

The [research note](../research/WF-021-yii-native-adapter-seams-research.md) selects capability-scoped Yii
providers, a native DB transactional UnitOfWork and URL generator, conditional mail/view adapters, and a
neutral PSR-7 JSend lane shared with Slim. It keeps provider-neutral adapters for the remaining surface and
holds Yii Queue integration as experimental until the unreleased core and a broker adapter receive stable
compatible tags.
