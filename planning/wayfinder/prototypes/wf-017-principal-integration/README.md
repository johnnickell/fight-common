# WF-017 principal-integration prototype

> **PROTOTYPE — wipeable evidence, not production authentication or supported starter adapters.**

## Question

Can each selected framework expose its native authenticated identity through the same framework-neutral
`CurrentPrincipalProvider` without making a shared aggregate implement a framework interface, while current
account state, authentication version, and session revocation are revalidated authoritatively per request?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
docker run --rm \
  -v "$PWD:/workspace" \
  -w /workspace/planning/wayfinder/prototypes/wf-017-principal-integration \
  fight-common composer install --no-interaction --no-progress

docker run --rm \
  -v "$PWD:/workspace" \
  -w /workspace/planning/wayfinder/prototypes/wf-017-principal-integration \
  fight-common php run.php
```

The runner executes all five native-boundary candidates and writes one machine-readable receipt per lane.

## Verdict

All five candidates pass with one unchanged portable provider/value boundary:

- Symfony reads the starter-owned security user from native token storage.
- Laravel reads a native `RequestGuard` user.
- Yii reads the identity installed by its authentication middleware on the PSR-7 request.
- CodeIgniter uses its documented authentication-implementation/user-ID convention through a starter-owned
  filter/service adapter.
- Slim uses a starter-owned PSR-15 authentication middleware request attribute.

The native identity is only a request-scoped credential reference containing `UserId`, session identity, and
the authentication-version claim. The provider reloads authoritative account/session state and returns an
immutable portable `AuthenticatedPrincipal` snapshot. Every lane accepts the valid active session and denies
anonymous, missing-user, disabled-user, stale-version, revoked-session, and wrong-session-owner cases.

This selects starter-owned provider adapters and does not justify a Fight Common adapter or any framework
interface on the shared User aggregate.

## Deliberate limits

- The in-memory authoritative store models the required lookup; database repositories and locking were proven
  separately and are not repeated here.
- This does not verify JWT parsing/signatures, login credentials, refresh rotation, cookies, CSRF/CORS,
  framework kernel/filter ordering, or HTTP responses.
- The CodeIgniter lane proves the documented identity convention and adapter shape, not a complete Shield
  installation or Shield-owned authorization model.
- Authorization policy, private realtime credentials, the React client, and the end-to-end login walking slice
  remain open WF-017 lanes.
