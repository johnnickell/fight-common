---
id: TICKET-00039
epic: EPIC-00008
title: Present Safe Errors Across HTTP Adapters
status: ready-for-agent
---

# Present Safe Errors Across HTTP Adapters

## Problem statement

Current Symfony and Laravel error controllers, the deprecated Symfony controller and PSR-15 JSendErrorMiddleware
copy arbitrary exception messages into public error bodies. A synthetic runtime exception containing a fake
password marker was reproduced verbatim in all four 500 responses. Consumers need explicit production-safe
handling with correct statuses, headers and diagnostics, without silently breaking published legacy behavior.

## Solution and boundaries

- Provide opt-in production-safe error presentation in the next minor, using
  [TICKET-00038](00038-TICKET.md)'s transport-neutral categories and explicitly supported native framework failures.
  Keep HTTP status/headers and native response creation in Adapter; reuse the existing JSend semantic envelope.
- Share the public-safe classification/projection policy across canonical Symfony, Laravel and PSR-15 handling.
  Preserve framework lifecycle/JSON negotiation and documented native response types. Cover deprecated Symfony
  paths in the migration/compatibility evidence; do not remove or silently change their legacy behavior in the minor.
- Recognized application categories receive fixed safe defaults. Any custom public message/data requires deliberate
  projection; the presence of a recognized class, getMessage text, integer exception code or previous exception is
  not permission to publish it. Unknown Throwable defaults to a generic safe 500 response.
- Translate authentication-required to 401, permission-denied to 403, intentionally missing resource to 404, state
  conflict to 409, resource-gone to 410, failed precondition to 412, rate limiting to 429, unexpected/server failure
  to 500 and temporary service unavailability to 503. Reconcile existing validation with syntactically invalid
  input (400) and business-rule rejection (422) explicitly, without changing legacy validation behavior.
- Framework routing, method/content negotiation, size rejection and gateway outcomes stay at their owning HTTP
  boundary. Support deliberate status/header translation there rather than making HTTP-only categories Application
  errors. Do not automatically map an arbitrary timeout to 504, storage lookup to 404, or exception code to status.
- Expected request/application refusals use JSend fail; unexpected server/infrastructure failures use error under
  [ADR 0018](../adr/0018-neutral-jsend-envelope-and-native-response-boundary.md). Use explicit safe presentation
  data, not exception objects or raw entities. Agent OS's current error-envelope choices are not Common authority.
- Preserve or deliberately supply required protocol metadata through safe translation: authentication challenges,
  Allow for method failures and valid retry metadata where supported. Authentication challenge policy/credentials
  remain consumer-owned; Common must not invent a Bearer scheme for every 401. Validate projected metadata and
  do not indiscriminately forward arbitrary exception headers or contextual values.
- Own redaction-aware diagnostic reporting explicitly, with one established logging path and no lost/duplicated
  reporting. Public output remains sanitized even if diagnostics contain internal causes; do not blindly expose
  throwable context/traces through either HTTP output or an unredacted logger.
- Production-safe behavior is the default of the new safe path. Development details, if provided, require explicit
  trusted configuration and must not be enabled by client input or inferred accidentally from unrelated settings.
- Preserve published legacy constructor/service behavior in the minor. Clearly document/deprecate unsafe legacy
  handling and provide concrete opt-in wiring. Replacing legacy defaults is reserved for the major after the required
  deprecation window. This planning approval is not a security compatibility exception or an immediate default fix.
- Exclude application authorization/authentication policy, new permission names, business retries/transactions,
  changing JSend/MCP protocols, adding unrequested framework-wide handlers and downstream repository migrations.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer configures safe Symfony/Laravel/PSR-15 handling | N/A; adapter composition | N/A; no business query | N/A; no domain mutation | Select explicit safe handling without changing existing legacy composition. |
| Handler throws a declared application refusal with sensitive diagnostic context | Existing consumer command, if applicable | Existing consumer query, if applicable | N/A; error presentation adds no event | Return the category's safe fail projection and correct HTTP outcome; never raw diagnostics. |
| An unknown Throwable or server failure reaches the safe boundary | Existing consumer workflow; unchanged | Existing consumer workflow; unchanged | N/A; no invented event | Generic safe 500 error plus the owned redaction-aware diagnostic path. |
| Framework raises a supported method/authentication/rate-limit failure | N/A; transport rejection | N/A; transport classification | N/A; no domain mutation | Safe output with correct status and required, validated protocol metadata. |
| Consumer deliberately customizes public presentation or development diagnostics | N/A; trusted composition | N/A; local projection | N/A; no domain mutation | Only explicitly projected safe data/details appear under trusted configuration. |
| Existing consumer retains a legacy handler during the minor | Existing workflow unchanged | Existing workflow unchanged | Existing workflow unchanged | Preserve compatibility while clearly warning that arbitrary-message legacy output is not production-safe. |

Response creation and diagnostics are technical effects, not business commands/events or persistence/schema
migrations. The boundary does not authorize operations, undo earlier command effects, or replace application
failure/recovery semantics. Redaction and logging composition must be explicit rather than a new policy engine.

## Validation and permissions

- Treat arbitrary throwable messages, causes, codes, request data and headers as non-public by default. Validation
  maps also require an explicit safe-data policy; do not assume every validator message is secret-free.
- Classify failure intent by declared type/registration, not by diagnostic string matching or arbitrary numeric
  codes. Document deliberate existing exception mappings; an auth infrastructure fault is not automatically 401.
- Keep consumer identity, challenges, availability policy and business authorization outside the handler. A generic
  404 may intentionally conceal resource existence, but the consumer owns that decision.
- Validate projected status/header/structured data and safe serialization. Unexpected mapping/diagnostic failures
  must not trigger a fallback that publishes the original diagnostic or returns a misleading successful response.

## Dependencies and sequencing

[TICKET-00038](00038-TICKET.md) supplies the reusable Application vocabulary; define its public category meanings
before HTTP mapping. This TICKET owns projection, integration, diagnostics and the minor migration together, not
just a status lookup table. It has no prerequisite on new temporal, financial, JSON or contact values.

John approved three complete feature TASKs on 2026-10-06:

- [TASK-00143 — Present Safe Errors Through PSR-15](../tasks/00143-TASK.md) depends on
  [TASK-00142](../tasks/00142-TASK.md) and delivers shared policy/diagnostics through a usable PSR-15 path.
- [TASK-00144 — Integrate Safe Symfony Error Handling](../tasks/00144-TASK.md) depends on TASK-00143 and owns
  canonical exception lifecycle/negotiation, native translation and deprecated-path migration/compatibility.
- [TASK-00145 — Integrate Safe Laravel Error Handling](../tasks/00145-TASK.md) depends on TASK-00143 and owns
  native rendering/reporting composition, translation and legacy controller/provider compatibility.

Symfony and Laravel can proceed independently after the shared PSR-15 slice. No execution priority is assigned.
Every TASK includes its complete integration, tests, public/behavioral classification, docs/CONTEXT.md, exact coverage,
full gate and independent review; there is no mapper-only prefactor or final unowned documentation/evidence TASK.

Approved shared mapping keeps existing structured ValidationException and InvalidInput at 400, RuleViolation at
422, and the remaining category/status pairs stated above. Expected refusals use fail; ServerError/unknown Throwable
and ServiceUnavailable use error. Raw field-error maps are not automatically safe. Native framework failures have
an explicit documented allowlist and validated transport metadata, not unrestricted status/header forwarding.
Reporting has one redaction-aware owner in each supported composition. New paths are production-safe by default;
any offered development details require trusted configuration. Legacy defaults remain functional during the minor.

Use the existing JSend envelope/native response contracts from ADR 0018 and namespace/public-path policy from
[ADR 0023](../adr/0023-service-container-and-framework-adapter-namespaces.md). Do not reopen completed MCP/framework
TICKETs or require an unrelated consumer migration. Preserve existing support boundaries and qualify affected
framework integrations under the project profile and
[ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md); Common fixture evidence is not a claim
that a downstream application's middleware/logging deployment has been secured.

## Acceptance and evidence

- Begin leak containment with failing regressions for the new safe-handling contract using synthetic sensitive
  messages, causes and data. Keep separate legacy compatibility assertions; do not delete them to declare a break
  compatible. Reproduce the original disclosure and prove the new opt-in path contains it.
- Cover every supported application category, unknown RuntimeException/Throwable, existing validation failures and
  explicitly supported framework failures. Assert safe JSend shape, exact chosen HTTP status, required header
  behavior and non-disclosure of diagnostic messages/causes/codes/context.
- Exercise actual Symfony exception-event/JSON negotiation integration, Laravel native responses and PSR-15
  middleware. Include non-JSON Symfony handling and deprecated-path migration/compatibility evidence, not just a
  direct mapper test. Preserve existing supported framework lifecycle/registration contracts.
- Exercise explicit public customization, validation-data projection, trusted development configuration and the
  absence of client-controlled debug activation. Test diagnostic ownership/redaction and failure paths without
  arbitrary secrets reaching response/log formatters.
- Document the exact safe wiring, category mapping, public-message policy, JSend fail/error distinction, challenge/
  Allow/retry metadata, diagnostic ownership, unsafe legacy limitations and planned major-default migration.
  Update affected HTTP/validation documentation and CONTEXT.md when the capability is implemented.
- Classify declarations, constructors, output/exception behavior and diagnostics under
  [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
  [ADR 0028](../adr/0028-clear-package-release-decisions.md) does not grant a blanket security exception: any
  immediate incompatible default remediation requires separate assessment/authorization.
- Each implementation TASK owns focused integration/behavior evidence, the complete `./bin/build` gate, exact
  owned-production coverage and independent review. No release certification, packaging tests or deployment is
  required or authorized by this requirement record.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00143](../tasks/00143-TASK.md) | Present Safe Errors Through PSR-15 | ready-for-agent |
| [TASK-00144](../tasks/00144-TASK.md) | Integrate Safe Symfony Error Handling | ready-for-agent |
| [TASK-00145](../tasks/00145-TASK.md) | Integrate Safe Laravel Error Handling | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved this added requirement area and the additive-safe-minor/legacy-default-major transition
in [EPIC-00008](../epics/00008-EPIC.md)'s nine-TICKET split. The inspected revision was
`e2160a70ce8314a84964bebf2eb5414e98e97178`. Direct PHP probes in the existing fight-common container returned the
synthetic diagnostic verbatim from canonical Symfony, Laravel, deprecated Symfony and PSR-15 handlers. This was
focused reproduction, not a live downstream exploit, full build or complete security audit. Host PHP 8.4 could not
load the PHP 8.5 dependencies; the successful reproduction used the project's PHP container without bypassing its
platform check. Source pointers: `src/Adapter/Http/Symfony/Controller/ErrorController.php`,
`src/Adapter/Http/Laravel/Controller/ErrorController.php`, `src/Adapter/HttpKernel/ErrorController.php` and
`src/Adapter/Middleware/Psr15/JSendErrorMiddleware.php`; Symfony subscribers delegate to the relevant controller.
Existing docs/tests explicitly expose diagnostic messages, so the behavior cannot be silently reclassified as a
compatible default repair. At that requirement checkpoint this was ready for TASK decomposition only; no handler
had been repaired or consumer secured.
No source change, commit, publication or release is authorized. Child status is generated above and parent
completion follows [planning conventions](../CONVENTIONS.md).

2026-10-06: John approved TASK-00143 through TASK-00145 and the recommended shared contracts recorded above. All
three are ready-for-agent with true dependencies, not yet executable: TASK-00143 waits for TASK-00142, while
TASK-00144/TASK-00145 wait for TASK-00143. This completes this TICKET's decomposition and EPIC-00008's nine-TICKET
planning phase, not implementation acceptance. Currency's unrelated source/data reuse decision remains needs-info
on the Board, and StreamId retains its following-major execution constraint.

Current source/tests confirm raw-message and validation-map legacy output, Symfony JSON/XHR negotiation and
Laravel's capability-scoped HttpServiceProvider; these are inspection facts, not fresh leak regressions, a full
product build/coverage result or downstream security qualification. Each child retains regression-first safe-path
containment with separate legacy evidence, required framework/support limits, complete verification and review.
No runtime source change, implementation branch, worktree, commit, publication, merge, certification, signing,
release, deployment or consumer migration is authorized or claimed by this planning approval.

Planning-only verification passed: `./bin/planning-check --write` and `./bin/planning-check` reported 185 records /
29 active, generated views refreshed/current, and tracked/new-TASK whitespace checks passed. All three new TASKs
appear in Waiting with the approved dependencies; the generated decomposition frontier is empty. This is planning
validation only, not fresh product/full-gate coverage or HTTP leak-containment evidence.
