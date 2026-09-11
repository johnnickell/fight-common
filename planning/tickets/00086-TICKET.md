---
id: T-00086
prd: PRD-00015
title: Complete Laravel Async Messaging and Private Publication Ownership
status: done
blocked_by:
---

# Complete Laravel Async Messaging and Private Publication Ownership

## Outcome

Move the reusable Laravel implementations of Fight asynchronous command dispatch, asynchronous event dispatch,
and private publication into Fight Common so Laravel starters only select providers and configure their own
handlers, queues, channels, and authorization.

## Scope

- In scope: Laravel asynchronous command/event adapters, private publisher, capability-provider bindings,
  conformance and provider tests, public documentation, and package planning evidence.
- Out of scope: application handlers, queue migrations, broker topology, retry policy, workers, channel
  authorization, routes, and starter-owned configuration.

## Acceptance Criteria

- [x] `AsynchronousCommandBus` resolves to a reusable Laravel adapter that submits the complete command envelope
      through the existing post-commit queue job.
- [x] `AsynchronousEventDispatcher` resolves to a reusable Laravel adapter that submits the complete event
      envelope and retains synchronous fan-out behind the neutral worker handler.
- [x] `PrivatePublisher` resolves to a reusable Laravel adapter that uses Laravel's private-channel convention
      without owning application authorization policy.
- [x] Messaging and broadcasting providers activate only their bounded capabilities.
- [x] Laravel remains an optional production dependency and every new public class has exact coverage.
- [x] Documentation makes the Fight Common/starter ownership boundary explicit.
- [x] `./bin/planning-check` and the canonical `./bin/build` pass.

## Verification

- Focused Laravel adapter and provider tests.
- `./bin/planning-check`.
- `./bin/build` with exact coverage and architecture enforcement.

## Completion Notes

Fight Common now provides the Laravel asynchronous command bus, asynchronous event dispatcher, and private
publisher through the bounded messaging and broadcasting providers. Focused adapter/provider verification passed
with 10 tests and 48 assertions. The canonical build passed all quality, architecture, compatibility, and exact
coverage gates with 4,062 tests and 15,272 assertions.
