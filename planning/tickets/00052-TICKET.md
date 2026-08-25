---
id: T-00052
prd: PRD-00014
title: Publish Capability-Scoped Symfony Service Container Paths
status: done
blocked_by: T-00047
---

# Publish Capability-Scoped Symfony Service Container Paths

## What to Build

Publish every existing Symfony compiler pass under `Adapter\ServiceContainer\Symfony`, separated by the bounded
capability it registers. A Symfony project explicitly selects command, query, event, event-mapping, or templating
composition while every superseded public compiler-pass identity remains functional throughout `1.x`.

## Acceptance Criteria

- [x] Command-handler, command-filter, query-handler, query-filter, and event-subscriber compiler passes are
      published under the canonical Symfony service-container namespace.
- [x] Event-mapping and template-helper compiler passes use the same canonical service-container grouping.
- [x] Each compiler pass registers only its bounded capability and does not activate unrelated integrations or
      optional packages.
- [x] Real-container conformance covers tag discovery, priorities, references, duplicate and missing routes,
      produced definitions, repeated compilation, and failure translation for each capability.
- [x] Event mapping still composes framework-free providers through the portable Event Mapper contract.
- [x] Templating still composes helpers through the portable Template Engine contract without adding framework
      dependencies to inward layers.
- [x] Every superseded compiler-pass FQCN remains independently loadable, constructible, registerable, and
      documented as deprecated throughout `1.x` without a runtime notice.
- [x] Compatibility choices preserve service identity, reflection, extension, attributes, and real registration;
      identity-sensitive cases use explicit compatibility classes when an alias is insufficient.
- [x] No aggregate Symfony Bundle or activate-everything compiler pass is introduced.

## Outcome

Published seven capability-scoped Symfony compiler-pass paths, retained independently constructible deprecated
`1.x` compatibility classes, and reconciled the complete public-API manifest. The full submit gate passed with
3,671 tests, 13,180 assertions, and exact 17,210/17,210 statement coverage.

## Verification

Full submit gate, `./bin/planning-check`, one-capability-at-a-time Symfony container conformance, optional-package
absence probes, architecture validation, and installed-package old/new FQCN probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.

## Decision Sources

WF-019, WF-023, WF-024, ADR 0023, and ADR 0024.
