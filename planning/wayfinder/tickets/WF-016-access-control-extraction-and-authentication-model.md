# Specify the Fight AccessControl extraction and authentication model

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Audit Fight Common contracts and the 1.2 compatibility envelope](WF-014-fight-common-contract-and-compatibility-audit.md), [Define the portable AccessControl and persistence boundaries](WF-012-access-control-and-persistence-boundaries.md)

## Question

What exact Domain, Application, session, authentication, authorization, and reference-data contracts
belong in the first public Fight AccessControl package?

## Must decide

- source comparison across `project`, Fight CMS, and Omphalos, selecting the strongest current
  behavior without copying project-specific features;
- removal of Symfony Security and token-storage dependencies from shared layers;
- whether internal Doctrine Collections remain and the exact framework-neutral public collection
  surface;
- aggregate, command, query, event, repository, read-model, password, account-state, authorization,
  and authenticated-principal contracts in `0.1.0`;
- reusable catalog reconciliation inputs and results, stable UUID ownership, collision rules,
  dry-run, custom-record preservation, and UnitOfWork behavior;
- required cookie-session profile and its framework integration boundary;
- optional stateless profile informed by StageOne Portal: short-lived in-memory access JWT, opaque
  HttpOnly refresh credential, hashing at rest, token-family rotation, atomic reuse detection,
  revocation, CSRF and Origin defenses, logout scope, and password-change effects;
- frontend single-flight refresh, awaited request retry, clock skew, bootstrap identity, multiple tabs,
  and authorization-display versus server-authority rules; and
- which security-sensitive behavior requires dedicated threat modeling and primary-source review.

## Resolution boundary

Produce the package extraction specification and authentication state model. Inspect only StageOne's
authentication boundary and do not publish, copy, or derive NFT or proprietary business behavior. Do
not move source or create the new package while resolving this ticket.
