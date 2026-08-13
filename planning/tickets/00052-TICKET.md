---
id: T-00052
prd: PRD-00014
title: Publish Canonical Symfony Event Sourcing and Templating Paths
status: ready-for-agent
blocked_by: T-00047
---

# Publish Canonical Symfony Event Sourcing and Templating Paths

## What to Build

Publish capability-first Symfony paths for event-mapping and template-helper dependency injection while
retaining their old public paths. Consumers can register either compiler pass and receive the same portable
Event Mapper and Template Engine composition through `1.x`.

## Acceptance Criteria

- [ ] The event-mapping compiler pass is available under the canonical
      EventSourcing/Symfony/DependencyInjection capability path.
- [ ] The template-helper compiler pass is available under the canonical
      Templating/Symfony/DependencyInjection capability path.
- [ ] Both old FQCNs remain independently loadable, functional, and documented as deprecated through `1.x`.
- [ ] Real-container probes cover service discovery, tags, references, ordering, registration output, repeated
      compilation, and relevant service identities for old and new paths.
- [ ] The event-mapping path still composes framework-free mapping providers through the portable Event Mapper
      contract.
- [ ] The templating path still registers helpers through the portable Template Engine contract without adding
      framework dependencies to inward layers.
- [ ] Compatibility mechanisms are justified by registration and identity evidence, with explicit forwarding
      behavior where a pure alias is insufficient.
- [ ] No runtime deprecation warning is emitted and neither legacy path is removed before `2.0.0`.

## Verification

Full submit gate, `./bin/planning-check`, Symfony container integration tests for both compiler passes,
architecture validation, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
