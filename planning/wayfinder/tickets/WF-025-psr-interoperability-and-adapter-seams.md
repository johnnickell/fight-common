# Research PSR interoperability and adapter seams

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the service-container and framework-adapter namespace model](WF-019-service-container-and-adapter-namespace-model.md)

## Question

Which accepted PHP-FIG standards can Fight Common consume directly, implement directly, or bridge through a
lossless adapter, and which standards do not match an existing Fight capability closely enough to claim
support?

## Research scope

Audit every currently accepted PSR against the live Domain, Application, Adapter, Composer, documentation, and
test surfaces. Classify each as a coding/package standard, direct inward dependency, Fight implementation,
inbound adapter, outbound adapter, conformance-only opportunity, future capability, or semantic mismatch.
Cover PSR-3, PSR-6, PSR-7, PSR-11, PSR-13, PSR-14, PSR-15, PSR-16, PSR-17, PSR-18, and PSR-20 explicitly.
Name honest additive canonical namespaces and compatibility implications where an adapter is justified.

Record findings in
`planning/wayfinder/research/WF-025-psr-interoperability-and-adapter-seams-research.md`.

## Resolution

Resolved by
[PSR interoperability and adapter seams research](../research/WF-025-psr-interoperability-and-adapter-seams-research.md).
Fight Common will use accepted standards directly where they are the honest inward contract, publish only
lossless standard adapters, and retain framework-native integrations where a PSR does not define the needed
lifecycle or behavior. The research selects shared PSR-15 middleware, a PSR-17 JSend response factory,
canonical PSR-6 and PSR-16 cache adapters, and a synchronous PSR-18 view over Fight's HTTP client. It rejects
a PSR-14 messaging bridge and the reverse PSR-18-to-Fight async claim as semantic mismatches.
