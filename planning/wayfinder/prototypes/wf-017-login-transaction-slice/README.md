# WF-017 login transaction slice prototype

> **PROTOTYPE — wipeable integration evidence, not complete starter applications or supported adapters.**

## Question

Can the previously selected persistence, transaction, and native HTTP response seams compose around one
unchanged email/password login handler in Symfony, Laravel, Yii, CodeIgniter, and Slim while successful
session creation and its required audit record remain atomic?

## Run

From the Fight Common repository root, with the repository's `fight-common` image available:

```bash
php planning/wayfinder/prototypes/wf-017-login-transaction-slice/run.php
```

The runner installs the retained locked dependency lanes, executes the same login probe through all five
native database transaction and response APIs, and writes machine-readable receipts under `receipts/`.

## Passing verdict

All five lanes canonicalize the email address, verify the password, atomically insert one refresh session and
one successful-login audit record, return a 15-minute access-token response, and set an opaque refresh cookie
with `Secure`, `HttpOnly`, and `SameSite=Strict`. Invalid credentials return the same generic failure and make
no durable change. A forced audit write failure rolls the session insert back, and a nested transaction fails
explicitly.

The unchanged handler and outcome contain no framework branch. Symfony and Slim use Doctrine DBAL for this
composition probe; Laravel uses its database transaction API; Yii uses Yii DB; and CodeIgniter uses explicit
transaction status checks. Each lane maps the outcome through its selected native response type.

## Deliberate limits

- This composes previously proven seams through native framework components, but it does not boot five full
  application kernels or prove native route discovery, middleware/filter ordering, or production caches.
- SQLite keeps the integration probe disposable. PostgreSQL/MySQL schema parity was proven separately, but
  this prototype does not rerun the complete login slice against both engines.
- The access token is a labelled placeholder. JWT signing, verification, key rotation, trusted-proxy
  handling, throttling, CSRF, and cookie deletion remain runtime evidence.
- It does not run a React SPA or browser automation. The required form submission, redirect, authenticated
  home state, fake credentials, local URL, and human UAT card remain part of the five starter walking slices.
- No production Fight Common or Fight AccessControl change is justified by this prototype.
