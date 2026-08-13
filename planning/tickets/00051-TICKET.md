---
id: T-00051
prd: PRD-00014
title: Publish Canonical Symfony Messaging Paths
status: ready-for-agent
blocked_by: T-00047
---

# Publish Canonical Symfony Messaging Paths

## What to Build

Publish capability-first Symfony paths for the messaging compiler passes, Messenger command bus, event
dispatcher, serializer, and handlers while retaining every old public path. A Symfony application can register
the canonical or legacy integration and observe the same routing, serialization, dispatch, and handler behavior.

## Acceptance Criteria

- [ ] The five command, query, and event registration compiler passes are available under canonical
      Messaging/Symfony/DependencyInjection paths.
- [ ] The Messenger command bus, event dispatcher, message serializer, command handler, and event handler are
      available under capability-appropriate Messaging/Symfony paths.
- [ ] Every legacy FQCN remains independently loadable, functional, and documented as deprecated through `1.x`.
- [ ] Compiler-pass probes use a real Symfony container and cover registration, tag discovery, service identity,
      attributes where relevant, duplicate or missing routes, and produced definitions.
- [ ] Bus, dispatcher, serializer, and handler probes cover construction, routing, message round trips,
      invocation, failures, and old/new interoperability.
- [ ] Aliases are used only where construction, `instanceof`, reflection identity, extension, and registration
      evidence prove them sufficient; identity-sensitive cases use an explicit compatibility design.
- [ ] No runtime deprecation warning is emitted and no legacy declaration is removed before `2.0.0`.
- [ ] All old/new public identities and designated behaviors are linked to stable compatibility findings.

## Verification

Full submit gate, `./bin/planning-check`, Symfony container and Messenger integration tests, serialization and
routing behavior tests, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
