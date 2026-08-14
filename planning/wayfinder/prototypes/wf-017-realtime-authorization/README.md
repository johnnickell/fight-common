# WF-017 realtime subscription authorization prototype

> **PROTOTYPE — wipeable credential-boundary evidence, not a supported realtime starter.**

## Question

Can all five starter compositions authorize the same `LIST_USERS` page subscription from a freshly
revalidated principal, deliver the credential through their selected native boundary, and deny the next
authorization immediately after authoritative session revocation without leaking framework types into the
portable authorization decision?

## Run

From the Fight Common repository root, with the `fight-common` image already built:

```bash
docker run --rm -v "$(pwd):/app:delegated" -w /app fight-common \
  php planning/wayfinder/prototypes/wf-017-realtime-authorization/run.php
```

The runner writes one machine-readable receipt per framework under `receipts/`.

## Compared delivery boundaries

- Symfony, Yii, CodeIgniter, and Slim mint the already-proven Mercure 1.0 OAuth 2 token with one exact
  `subscribe` match. Their native response sets `__Secure-mercure_access_token` as `Secure`, `HttpOnly`,
  `SameSite=Strict`, and scoped to `/.well-known/mercure`.
- Laravel uses its native `PusherBroadcaster` authorization path for the selected Reverb private channel and
  returns the signed `/broadcasting/auth` JSON shape. The receipt verifies the signature independently.
- All five call the same framework-neutral authorization decision after the native authentication reference
  has been revalidated into an immutable principal.

## Verdict

The seam passes in all five lanes. Select a same-origin reverse proxy for Mercure in Symfony, Yii,
CodeIgniter, and Slim so the application can issue the secure hub cookie without cross-origin cookie or CORS
machinery. Keep Laravel on the native Reverb/Pusher private-channel authorization route. No Fight Common or
Fight AccessControl contract change is justified.

Revocation is fail-closed for new authorization and renewal. It cannot retroactively invalidate a credential
or socket already accepted by a realtime server: the Mercure credential is therefore limited to 60 seconds,
while Reverb needs a separate disconnect or bounded reconnect policy if immediate termination of an open
socket is required. That residual-connection question remains explicit rather than being mislabeled as
solved by the authorization endpoint.

## Deliberate limits

- The preceding protocol prototype already exercised the Mercure 1.0 alpha hub; this lane does not start it
  again or claim the alpha is production-ready.
- This does not publish an event, prove post-commit dispatch, run Reverb, exercise browser reconnects, or
  demonstrate two-browser invalidation and client refetch.
- It does not yet define the public realtime envelope or generated TypeScript union.
- It uses deterministic prototype identities and signing keys. No credential is suitable for deployment.
