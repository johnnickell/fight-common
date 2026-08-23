# Research Laravel-native adapter seams

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the service-container and framework-adapter namespace model](WF-019-service-container-and-adapter-namespace-model.md)

## Question

Which Laravel facilities can implement or wire each public Fight Common capability as a reusable optional
adapter, and what lifecycle, serialization, transaction, queue, worker, and package-discovery constraints must
the adapter preserve?

## Research scope

Use current primary Laravel and package documentation. Cover authentication, cache, service container,
UnitOfWork and persistence, filesystem and file storage, HTTP client, request/response/JSend, middleware,
observability and logging, process management, routing and URL generation, mail, SMS/notifications, sockets
and broadcasting, templating, synchronous messaging, queued command/event delivery, serialization, retries,
failed jobs, and post-commit dispatch. Distinguish a reusable Fight Common adapter from starter-only
configuration and from capabilities already satisfied by a neutral PSR or provider adapter.

Record findings in `planning/wayfinder/research/WF-020-laravel-native-adapter-seams-research.md`.

## Resolution

The [research note](../research/WF-020-laravel-native-adapter-seams-research.md) selects queued command Jobs
and queued event listeners delegating to neutral Fight consumers, a narrow Laravel transactional UnitOfWork,
and opt-in capability providers. It identifies the native Laravel adapters that add a real translation seam,
the provider-neutral adapters to reuse, and the configuration and operations that remain starter-owned.
