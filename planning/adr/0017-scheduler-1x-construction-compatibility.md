# ADR 0017: Scheduler 1.x Construction Compatibility

- Status: accepted
- Date: 2026-08-12

## Decision

Fight Common `1.2.0` restores the exact published `1.1.0` `Scheduler` constructor, including the optional
third-position logger and optional sixth-position process factory. Existing positional and named
construction, two-argument construction, custom process factories, and documented command execution remain
functional throughout `1.x`.

Portable process execution is introduced additively through a named `Scheduler::withProcessRunner(...)`
construction path. New composition uses the Application-owned `ProcessRunner` port and a selected Adapter
implementation. It does not reinterpret any legacy constructor argument or add a runner parameter to that
constructor.

The legacy constructor's conditional Symfony Process execution remains as a deprecated compatibility bridge
through `1.x`. This is a narrow exception for already-published behavior, not a general allowance for
Application code to acquire new framework dependencies. Fight Common `2.0.0` removes the legacy process
factory and compatibility execution path and may require `ProcessRunner` composition directly.

If implementation evidence cannot reproduce the `1.1.0` behavior while keeping that compatibility bridge
bounded, the required-runner design is deferred to `2.0.0`. It may not be certified as `1.2.0` in its current
constructor position.

## Consequences

Every supported `1.1.0` Scheduler construction style requires a consumer compatibility fixture against the
candidate. The fixture set includes positional and named optional arguments, two-argument construction, a
custom process factory, command output, and non-zero command failure behavior.

New framework compositions use `Scheduler::withProcessRunner(...)` and bind a portable runner explicitly.
The deprecated path remains functional for at least the `1.2` minor line and receives no new capabilities.
Documentation must distinguish the compatibility path from the recommended composition path without
emitting unreviewed runtime deprecation warnings.

## Rejected Alternatives

Keeping the current required third-position `ProcessRunner` was rejected because it breaks two-argument
construction, reinterprets the published logger position, shifts later positional arguments, and removes the
published process-factory seam.

Making the third-position runner nullable was rejected because it still reinterprets positional logger calls.
Appending an optional runner to the legacy constructor was rejected in favor of preserving the exact
constructor and exposing the new dependency through an explicit named construction path.

Silently disabling command jobs when no runner is supplied was rejected because it changes established
runtime behavior. Moving all repair to `2.0.0` remains the fail-closed fallback only if the compatibility path
cannot be implemented faithfully.
