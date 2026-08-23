---
id: T-00050
prd: PRD-00014
title: Publish Canonical Symfony HTTP Filesystem and Routing Paths
status: ready-for-agent
blocked_by: T-00049
---

# Publish Canonical Symfony HTTP, Filesystem, and Routing Paths

## What to Build

Publish the accepted capability-first Symfony paths for HTTP subscribers, middleware, controllers, local
filesystem, and URL generation while preserving every superseded public FQCN throughout `1.x`. A Symfony
consumer can register either identity and receive the same native lifecycle, response, filesystem, and routing
behavior without runtime deprecation noise.

## Acceptance Criteria

- [ ] Exception and validation subscribers are published under the canonical Symfony HTTP namespace.
- [ ] JSON request middleware is published under `Adapter\Middleware\Symfony`, and the error controller is
      published under `Adapter\Http\Symfony\Controller`.
- [ ] The Symfony filesystem and URL generator are published under their capability-first provider namespaces.
- [ ] Every superseded public FQCN remains independently loadable, functional, and documented as deprecated
      throughout `1.x` without emitting a runtime notice.
- [ ] Compatibility mechanisms are selected per declaration from construction, extension, reflection, attribute,
      serialization, and registration evidence rather than applying aliases indiscriminately.
- [ ] Real Symfony registration probes cover subscriber attributes and tags, controller resolution, middleware
      invocation, service identity, and old/new interoperability.
- [ ] HTTP probes preserve validation and exception translation, native response status, headers, encoded body,
      and the typed JSend boundary delivered by T-00049.
- [ ] Filesystem and URL-generation probes preserve success, failure translation, named-route parameters,
      absolute and relative output, and both public identities.
- [ ] No legacy declaration is removed before `2.0.0`, and no framework dependency enters an inward layer.

## Verification

Full submit gate, `./bin/planning-check`, Symfony container and HTTP-kernel integration tests, shared filesystem
and URL-generation conformance, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Sources

WF-019, WF-023, WF-024, ADR 0023, and ADR 0024.
