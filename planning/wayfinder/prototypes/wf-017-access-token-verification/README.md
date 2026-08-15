# WF-017 access-token verification prototype

> **PROTOTYPE — wipeable runtime evidence, not a supported authentication adapter.**

## Question

Can Fight Common's existing generic JWT encoder and decoder safely satisfy the AccessControl starter profile's
exact access-token checks and bounded overlapping-key rotation, or does that profile need a project-owned,
purpose-specific verifier?

## Run

From the Fight Common repository root, using the tracked dependencies:

```bash
php planning/wayfinder/prototypes/wf-017-access-token-verification/run.php
```

The runner creates disposable RSA keys in memory, exercises the current Fight Common HMAC adapter, exercises
one purpose-specific RS256 key-ring candidate, and writes secret-free machine-readable receipts under
`receipts/` for Symfony, Laravel, Yii, CodeIgniter, and Slim.

## Passing verdict

The current generic adapter is not sufficient for the accepted AccessControl profile. `JwtDecoder` validates
only the signature: an expired token and a token with the wrong issuer both decode successfully. `JwtEncoder`
does not emit a `kid` header and supports only HMAC algorithms, so it cannot express the selected asymmetric,
overlapping-key rotation policy.

The purpose-specific candidate rejects an expired or not-yet-valid token; the wrong type, issuer, audience,
algorithm, or purpose; an unknown key ID; a key outside its bounded verification overlap; missing identity
claims; a revoked session; and an authentication-version mismatch. It accepts the active signing key and the
previous key only within its declared overlap window. Cryptographic acceptance is followed by authoritative
user/session-state validation, so a still-unexpired access token never delays revocation.

Select a consumer-owned AccessControl access-token issuer/verifier port with exact policy inputs and a
replaceable asymmetric key ring. Do not widen Fight Common's generic `TokenEncoder`/`TokenDecoder` contracts
or silently treat their current adapter as the certified starter security profile.

## Deliberate limits

- Keys are generated in memory and never written to receipts. Deployment key loading, custody, rotation
  operations, and emergency revocation remain consumer-owned.
- The runner proves the framework-neutral verifier once and records the same result for five composition
  lanes; it does not boot five kernels or exercise native authentication middleware.
- It does not prove refresh-cookie CSRF/Origin enforcement, CORS, trusted proxies, route middleware ordering,
  browser storage, or React behavior. Those remain separate runtime and walking-slice evidence.
- It records the mismatch between the current decoder and `docs/auth.md`; it does not change production code
  or documentation.

