# WF-017 Mercure protocol-version prototype

> **PROTOTYPE — wipeable version evidence, not a supported realtime starter.**

## Question

Can the accepted Mercure 1.0 OAuth 2 private publish/subscription flow run with compatibility mode disabled,
and what version boundary follows from the current stable `v0.24.2` and testing-only `v1.0.0-alpha.3` images?

## Run

From the Fight Common repository root, with Docker available:

```bash
planning/wayfinder/prototypes/wf-017-mercure-version/run.sh
```

The runner starts each official image on an ephemeral loopback port, executes the same comparison, writes
one receipt per image, and removes each container before continuing.

## Compared credential shapes

- The released 0.x shape uses a bespoke `mercure.publish` / `mercure.subscribe` JWT claim and the
  `topic` subscription query parameter.
- The documented 1.0 shape uses an OAuth 2 access token (`typ: at+jwt`, issuer, audience, expiry, and
  `authorization_details`) and the `match` subscription query parameter.

Both tokens are short-lived, signed with the same disposable prototype key, and limited to the same exact
private topic. The comparison changes only the protocol-version shape.

## Verdict

The accepted Mercure 1.0 composition is runnable for prototype work against the official
`v1.0.0-alpha.3` public preview with compatibility mode disabled. Its OAuth 2 token publishes and receives the
private update, while the legacy claim and `topic` subscription parameter fail closed. Conversely, stable
`v0.24.2` accepts the legacy flow and rejects the modern flow.

Pin `v1.0.0-alpha.3` only in disposable WF-017 evidence. The upstream release explicitly says it is for
testing, not production, and may change before final 1.0.0. Do not turn compatibility mode on, do not fall
back to the rejected legacy protocol, and do not describe any starter using this alpha as production-ready.
Before a starter release, replace the alpha with a stable 1.0 hub and rerun the same receipt.

This does not challenge Laravel's selected Reverb composition and does not justify a Fight Common contract
change. The existing `Publisher` and `MercureHubPublisher` remain unchanged.

## Deliberate limits

- This is protocol/version evidence, not a complete Symfony, Yii, CodeIgniter, or Slim kernel.
- The 1.0 lane is an upstream public alpha expressly intended for testing, not a stable support selection.
- It does not yet prove browser cookie attributes, CORS, reconnect/recovery, two-browser invalidation, public
  envelope schemas, Laravel Reverb, or client cache refetch behavior.
- The release observation is date-bound; rerun the official release check before publishing a starter.
