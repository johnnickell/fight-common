# WF-017 private realtime publication prototype

> **PROTOTYPE — wipeable publication-seam evidence, not a supported realtime starter.**

## Question

Can one committed AccessControl event become the same minimal, versioned users-page invalidation in all five
starter compositions and publish privately through Mercure or Laravel Reverb without exposing PHP classes,
arbitrary metadata, or domain state? Is Fight Common's existing two-argument `Publisher` sufficient?

## Run

From the Fight Common repository root, with the `fight-common` image already built:

```bash
docker run --rm -v "$(pwd):/app:delegated" -w /app fight-common \
  php planning/wayfinder/prototypes/wf-017-realtime-publication/run.php
```

The runner rewrites one machine-readable receipt per framework under `receipts/`.

## Verdict

The transformer and delivery seam passes in all five lanes, but the existing `Publisher` is insufficient for
the accepted private-update requirement. `MercureHubPublisher::push()` constructs a public `Update`; changing
the existing interface signature would break implementors. The smallest compatible shape is an additive,
framework-neutral `PrivatePublisher::pushPrivate(topic, message)` port. A Mercure adapter sets `private: true`;
a Laravel project adapter delegates to the native Pusher/Reverb broadcaster on an already-authorized private
channel.

One `UserDeletedTransformer` owns one stable public name and schema, accepts only the users-page topic family,
and emits an invalidation payload plus allowlisted correlation identity. It does not serialize the domain
event or its metadata. Framework composition supplies the transport address: the Mercure topic URI or Reverb
private channel.

## Deliberate limits

- This invokes the subscriber only after a deterministic commit probe; it does not run an asynchronous worker,
  outbox, Mercure hub, or Reverb server.
- It proves native client request composition with in-process fakes, not network delivery or browser receipt.
- It does not generate JSON Schema or TypeScript unions, test schema drift, reconnect a browser, or refetch the
  authoritative users page.
- It does not define retry, dead-letter, or publication-failure policy.
