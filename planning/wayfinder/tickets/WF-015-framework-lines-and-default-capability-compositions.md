# Select supported framework lines and default capability compositions

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Audit Fight Common contracts and the 1.2 compatibility envelope](WF-014-fight-common-contract-and-compatibility-audit.md)

## Question

Which exact framework versions and native, portable, or third-party facilities form the one supported
default composition for each public Fight Common contract in each starter?

## Must decide

- current and previous maintained Laravel, Yii 3, CodeIgniter 4, Slim, and Symfony lines that satisfy
  Fight Common's PHP and Composer constraints;
- isolated locks and a combined root-resolution strategy that detects framework upgrade conflicts;
- native container and service registration, explicit handler maps, and optional build-time discovery;
- synchronous and asynchronous command and event dispatch, queue transports, retries, failure stores,
  worker commands, and operational monitoring;
- routing and URL generation, native SPA host templating, validation, mail, cache, HTTP clients,
  storage, SMS, process execution, scheduling, logging, metrics, health, and event-storage composition;
- Laravel ServiceProvider, Yii configuration provider, CodeIgniter `Config\Services`, and Slim explicit
  container responsibilities without adding a Symfony bundle;
- the one opinionated Slim stack, including Doctrine, Twig, queue, console, session, and security
  packages; and
- explicit unsupported blockers, if any, that make a framework integration incomplete rather than
  silently omitting a public contract.

## Resolution boundary

Produce a decision record and supported-composition worksheet with primary-source evidence. Do not
install framework packages, create starter repositories, or implement adapters while resolving this
ticket.
