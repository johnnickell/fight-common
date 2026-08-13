---
id: T-00049
prd: PRD-00014
title: Deliver Typed JSend Through the Symfony Response Boundary
status: ready-for-agent
blocked_by: T-00047
---

# Deliver Typed JSend Through the Symfony Response Boundary

## What to Build

Deliver one typed, framework-neutral JSend journey from an `Arrayable` presentation value or paginated
`ResultSet` through the semantic envelope to a Symfony-native response whose HTTP status is selected by the
controller. Preserve the existing Symfony raw-array response as a functional deprecated `1.x` path.

## Acceptance Criteria

- [ ] The base `Arrayable` contract promises `array<array-key, mixed>` and each existing implementation retains
      or declares its honest narrower list or map shape.
- [ ] `ResultSet<TRecord>` remains valid for arbitrary existing record types and preserves its current
      pagination fields and general-purpose `toArray` behavior.
- [ ] Immutable `JSendEnvelope` and `JSendStatus` types represent success, fail, and error without owning an HTTP
      status, headers, framework response, service lookup, or I/O.
- [ ] A single `Arrayable` presentation value becomes JSend data without pagination metadata.
- [ ] A `ResultSet` of `Arrayable` records projects each record through `toArray` and preserves page, per-page,
      total-page, total-record, and records fields.
- [ ] A non-`Arrayable` record fails only at the typed JSend presentation boundary and remains valid for every
      other `ResultSet` consumer.
- [ ] The envelope owns final JSON encoding with option `79` and throwing error behavior; invalid UTF-8 and
      unencodable values raise `JsonException` before native response construction.
- [ ] The canonical Symfony response accepts an envelope plus controller-selected status and headers, exposes
      ergonomic semantic factories, sets the native JSON content type, and uses the encoded body without
      re-encoding it.
- [ ] The legacy Symfony response retains raw arrays, its option-79 default, caller-selected encoding options,
      and existing public behavior throughout `1.x`, with PHPDoc/documentation deprecation but no runtime notice.
- [ ] Consumer probes cover exact success, fail, and error fields; null and optional data; single and paginated
      results; headers; controller-selected statuses; invalid encoding; and both response entry points.

## Verification

Full submit gate, `./bin/planning-check`, focused `Arrayable`, collection, `ResultSet`, envelope, and Symfony
response tests, plus installed-package legacy and typed JSend consumer probes.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
