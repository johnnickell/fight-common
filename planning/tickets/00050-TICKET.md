---
id: T-00050
prd: PRD-00014
title: Publish Canonical Symfony HTTP Filesystem and Routing Paths
status: ready-for-agent
blocked_by: T-00049
---

# Publish Canonical Symfony HTTP, Filesystem, and Routing Paths

## What to Build

Publish capability-first Symfony paths for the remaining HTTP integration, local filesystem adapter, and URL
generator while preserving every old public FQCN. A Symfony consumer can register and invoke either path through
`1.x` without changed behavior, identity surprises, or runtime deprecation noise.

## Acceptance Criteria

- [ ] The exception and validation subscribers are available under canonical Http/Symfony/EventSubscriber paths.
- [ ] The error controller and JSON request middleware are available under canonical Http/Symfony paths.
- [ ] The Symfony filesystem and URL generator are available under canonical Filesystem/Symfony and
      Routing/Symfony paths.
- [ ] Every legacy FQCN remains independently loadable, functional, and documented as deprecated through `1.x`.
- [ ] Compatibility mechanisms are selected per declaration from behavioral evidence rather than applying
      aliases indiscriminately.
- [ ] Subscriber and controller probes cover attributes, registration, service identity, construction,
      invocation, and promised extension behavior where applicable.
- [ ] Filesystem and routing probes cover construction, `instanceof`, behavior, exception translation,
      serialization where relevant, and both old and new names.
- [ ] No runtime deprecation warning is emitted and no legacy declaration is scheduled for removal before
      `2.0.0`.

## Verification

Full submit gate, `./bin/planning-check`, Symfony container and event-dispatcher integration tests, filesystem
and routing behavior tests, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
