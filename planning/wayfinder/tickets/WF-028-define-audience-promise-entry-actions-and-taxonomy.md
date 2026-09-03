# Define the audience, promise, entry actions, and component taxonomy

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Establish the presentation destination and ownership](WF-026-establish-presentation-destination-and-ownership.md), [Research documentation references and delivery constraints](WF-027-research-documentation-references-and-delivery-constraints.md)

## Question

Who must the Fight Common documentation serve first, what promise should it make, which actions should each
audience take on entry, and how should the component atlas organize the library?

## Must decide

- primary and secondary audience segments, including their starting knowledge and intent;
- one credible primary promise and the proof needed above the fold;
- the first actions for evaluation, installation, guided adoption, reference lookup, and contribution;
- the relationship among the repository README, documentation homepage, quick start, framework guidance, and
  component pages;
- a component-atlas taxonomy that reflects user problems without hiding the Domain, Application, and Adapter
  architecture; and
- which real content will be used in later visual comparisons.

## Resolution boundary

This ticket settles information intent and content hierarchy. It does not choose typography, color, logo,
layout styling, generator architecture, final copy, or implementation details.

## Resolution

Fight Common documentation serves experienced PHP developers and architects first: people who understand PHP,
Composer, and dependency injection, but may know nothing about Fight Common and need to evaluate or adopt one
framework-neutral capability quickly. Existing adopters seeking integration or reference guidance are the second
audience; contributors and maintainers are third. Recruiters and general open-source browsers belong to the
GitHub-profile adaptation rather than driving the documentation product.

The working promise is: **adopt focused PHP building blocks without coupling Domain or Application code to a
framework**. This is positioning input, not approved final copy. Above the fold, the documentation should prove
the promise with the three-layer architecture, representative real capabilities, supported integration paths,
and the shortest valid Composer installation rather than relying on generic quality claims.

The entry actions are:

1. **Explore Components** to evaluate the library through the complete component atlas.
2. **Start Building** through a short, framework-neutral Quick Start.
3. **Understand the Architecture** through a visual Hexagonal Architecture and CQRS guide.
4. Choose framework or framework-free integration guidance for adoption.
5. Search or browse a specific component for reference.
6. Enter the repository's contribution and development path when maintaining the project.

Each surface has a distinct job:

- the repository README establishes trust, summarizes the package, shows installation, and routes into the
  documentation;
- the documentation homepage communicates the promise, presents Architecture, Quick Start, and Component Atlas
  as prominent parallel routes, then displays the complete grouped atlas with directly visible component links;
- the Quick Start delivers one short framework-neutral working path and then branches into framework and
  component guidance;
- framework guidance owns activation, supported integrations, starter repositories, and composition without
  duplicating component semantics;
- component pages are the canonical detailed guides and references; and
- contributor guidance owns maintainer setup, standards, and delivery practices.

The component atlas is organized around user problems while keeping architectural ownership visible:

- **Model the Domain:** values, collections, specifications, repositories, and event sourcing;
- **Coordinate Application Behavior:** messaging, validation, serialization, and dependency injection;
- **Connect Systems:** HTTP, authentication, cache, files, file transfer, templating, routing, mail, SMS, and
  sockets;
- **Operate Workloads:** observability, processes, and scheduling; and
- **Integrate Frameworks:** framework support and framework-specific adapters.

Every atlas card and component page shows its `Domain`, `Application`, and/or `Adapter` ownership plus required
optional dependencies. Category cards expose their component links directly: for example, **Connect Systems**
shows **Mail** at a glance rather than requiring an intermediate category page.

Architecture is not an atlas component. It is a first-class conceptual learning path explaining Hexagonal
Architecture, CQRS, inward dependencies, ports and adapters, and how Fight Common composes those patterns, with
diagrams and links from relevant components. The coding standard belongs under contributor and engineering
guidance.

Component guides share this flexible content sequence, omitting only sections that do not apply:

1. the problem the component solves;
2. its Domain, Application, and Adapter placement;
3. required and optional dependencies;
4. the shortest framework-neutral example;
5. available adapters and framework composition;
6. behavior, failure modes, and operational concerns; and
7. related components and deeper reference material.

Later visual comparisons use real content from the complete grouped homepage, the diagram-led Architecture
guide, Mail as an approachable component article, Messaging as the dense multi-layer article stress test, and
the current Quick Start revised only enough to perform its agreed role.
