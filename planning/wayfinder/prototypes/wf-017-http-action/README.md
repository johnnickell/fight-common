# WF-017 native HTTP action prototype

> **PROTOTYPE — wipeable evidence, not production controllers or supported starter adapters.**

## Question

Can the same framework-neutral authorized users-list query produce consistent JSend `200`, `401`, and `403`
semantics through every selected framework's native HTTP action and response type, without turning Fight
Common's Symfony-backed `JSendResponse` into a cross-framework contract?

## Run

From the Fight Common repository root, with the repository's `fight-common` PHP 8.5 image available:

```bash
docker run --rm \
  -v "$PWD:/workspace" \
  -w /workspace/planning/wayfinder/prototypes/wf-017-http-action \
  fight-common composer install --no-interaction --no-progress

docker run --rm \
  -v "$PWD:/workspace" \
  -w /workspace/planning/wayfinder/prototypes/wf-017-http-action \
  fight-common php run.php
```

The runner executes all five native response candidates and writes one machine-readable receipt per lane.

## Verdict

All five candidates pass one unchanged `ListUsersQueryHandler` and `ListUsersOutcome` through their native
response boundary:

- authorized requests return HTTP 200 and JSend `success` with the users view;
- anonymous requests return HTTP 401 and JSend `fail` with `authentication_required`;
- authenticated principals without `LIST_USERS` return HTTP 403 and JSend `fail` with `forbidden`.

Symfony returns `JsonResponse`; Laravel returns its `JsonResponse`; Yii returns the PSR-7 response selected by
the application; CodeIgniter returns its native `Response`; Slim returns PSR-7. Each starter owns the thin
mapping from the portable authorization outcome to its native response. Fight Common's existing
`JSendResponse` remains a Symfony convenience and does not become the portable application result or a
required dependency for the other projects. No Fight Common contract change is justified.

## Deliberate limits

- The prototype invokes the action boundary directly. It records the selected native route declaration but
  does not boot five complete kernels or re-prove route discovery, middleware/filter order, or container
  compilation.
- Principal revalidation was proven separately. This uses the resulting immutable principal snapshot and
  proves only application authorization plus response mapping.
- Pagination parsing, validation errors, not-found/conflict mapping, OpenAPI generation, CORS/CSRF, cookies,
  login, and exception subscribers remain outside this bounded question.
- Private realtime authorization, the React client, and the complete end-to-end walking slice remain open.
