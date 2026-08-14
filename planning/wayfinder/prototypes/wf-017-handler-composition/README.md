# WF-017 handler-composition prototype

> **PROTOTYPE — wipeable evidence, not starter service configuration or supported adapters.**

## Question

Can each selected framework composition build one complete, inspectable command/query/event map for unchanged
portable Application handlers and reject missing, ambiguous, or duplicate registrations during boot, while
Slim remains explicit and performs no classpath scanning?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
php planning/wayfinder/prototypes/wf-017-handler-composition/run.php
```

The runner installs one isolated locked dependency set, boots five native container compositions, dispatches
one command, query, and event through each resolved map, exercises three invalid boot scenarios, and writes
machine-readable receipts under `receipts/`.

## Candidate compositions

- Symfony: compile-time autoconfiguration tags collected from the compiled service container.
- Laravel: project service-provider bindings and native container tags.
- Yii: tagged definitions in the normal `config/common/di` composition.
- CodeIgniter: one explicit project-owned `Config\\Services` factory returning the handler catalog.
- Slim: explicit PHP-DI definitions and handler-ID lists with autowiring disabled.

Every project owns its composition flavor. The portable handler classes and their message registrations are
identical in every lane. A small conformance compiler turns each native collection into the same observable
receipt and rejects an absent command handler, two handlers for one command, or the same event-subscriber
service registered twice.

## Deliberate limits

- The prototype proves one command, one query, and one event subscription, not the complete AccessControl map.
- It validates composition at boot in the runner; it does not wire complete framework application kernels or
  their production cache commands.
- Symfony proves compile-time discovery. The other four candidates use explicit project-owned lists or tags;
  none performs per-request classpath scanning.
- Multiple distinct subscribers for one event remain valid fan-out. Only duplicate registration of the same
  subscriber service is rejected.
- HTTP, principal/provider integration, realtime authorization, the React client, and the complete walking
  slice remain open WF-017 lanes.
