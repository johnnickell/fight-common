---
id: T-00085
prd: PRD-00014
title: Emit PSR-7-Compatible HMAC Request Headers
status: done
blocked_by:
---

# Emit PSR-7-Compatible HMAC Request Headers

## Outcome

Make `HmacRequestService` emit every signed request header as a PSR-7-compatible string while preserving the
existing canonical request and signature calculation.

## Scope

- In scope: HMAC request-header value types, a real PSR-7 signing journey, focused unit expectations, release
  notes, and the `1.2.0` compatibility record.
- Out of scope: the HMAC algorithm, authentication policy, nonce storage, deprecated webhook removal, starter
  repository changes, and release publication.

## Acceptance Criteria

- [x] `HmacRequestService` passes only string values to `RequestInterface::withHeader()`.
- [x] A real Guzzle PSR-7 request can be signed without an invalid-header exception.
- [x] The emitted timestamp remains the exact timestamp used by canonical request and signature calculation.
- [x] Existing body hashing, nonce, credential, authorization, and signature behavior remains unchanged.
- [x] The `1.2.0` changelog and compatibility records identify the corrected PSR-7 behavior.

## Verification

- Focused HMAC request-service tests, `./bin/planning-check`, and canonical `./bin/build` with exact statement
  coverage.

## Completion Notes

Completed on 2026-09-01. `HmacRequestService` now converts the outbound timestamp header to a string while
retaining the integer timestamp used to build and sign the canonical request. Strict mock expectations expose
the PSR-7 value type, and a real Guzzle PSR-7 request completes the signer-to-authenticator journey with body
hashing and normalized query order intact. The focused HMAC suite passed with 28 tests and 61 assertions. The
canonical `./bin/build` passed with 4,056 tests, 15,259 assertions, and exact 18,566/18,566 statement coverage.
