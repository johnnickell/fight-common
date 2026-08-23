# Research CodeIgniter-native adapter seams

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the service-container and framework-adapter namespace model](WF-019-service-container-and-adapter-namespace-model.md)

## Question

Which CodeIgniter facilities can implement or wire each public Fight Common capability as a reusable optional
adapter, and where must Fight Common use a neutral or standalone provider instead?

## Research scope

Use current primary CodeIgniter 4 and official package documentation. Cover `Config\Services`, authentication,
cache, Model/Query Builder transactions and UnitOfWork, filesystem and storage, HTTP client and responses,
filters or middleware, logging and observability, process management, routing, email, SMS, sockets, views,
synchronous messaging, queue delivery, serialization, retries, failed jobs, and worker lifecycle. Separate
official framework/package support from community packages and starter-owned configuration.

Record findings in `planning/wayfinder/research/WF-022-codeigniter-native-adapter-seams-research.md`.

## Resolution

The [research note](../research/WF-022-codeigniter-native-adapter-seams-research.md) selects a native
transactional UnitOfWork and Queue-backed command/event delivery as the first reusable adapters. It identifies
JSend/error handling, URL generation, and mail as prototype candidates; keeps neutral provider adapters where
CodeIgniter adds no matching contract; and uses explicit starter `Config\Services` delegation because nested
Fight Common service-container classes are not conventionally auto-discovered.
