---
id: TICKET-00035
epic: EPIC-00008
title: Validate Network and Contact Values
status: done
---

# Validate Network and Contact Values

## Problem statement

EmailAddress accepts quoted local parts but extracts them incorrectly when they contain an at-sign. Common also
lacks the agreed reusable IP-family and E.164 phone values. Consumers need dependable lexical values and
normalization without embedding transport addressing policy or changing existing SMS signatures.

## Solution and boundaries

- Repair EmailAddress local/domain extraction for accepted quoted addresses, including `"a@b"@example.com`.
  Retain local quotes/escape bytes and existing domain-literal bracket stripping. Preserve original-string
  representation, case-sensitive equality/hash, separate lowercase canonical() and existing accepted/rejected syntax.
- Add abstract IpAddress with final IpV4Address and IpV6Address implementations. Generic parsing selects the correct
  concrete family; family-specific parsing rejects the other family rather than coercing it.
- Normalize equivalent IPv6 spellings consistently for equality, hashing and reconstruction. IPv4-mapped IPv6
  remains IPv6, including equivalent dotted/hexadecimal spellings, and is not equal to the corresponding IPv4 value.
  Use dotted-decimal IPv4 and deterministic lowercase compressed IPv6 with existing ValueObject string/JSON behavior.
  Reject malformed/wrong-family input and contextual whitespace/CIDR/port/bracket/zone forms with DomainException;
  valid private/loopback/other address categories do not become forbidden through a new policy filter.
- Add E164PhoneNumber with whole-string grammar `\A\+[1-9][0-9]{0,14}\z`: a leading plus, nonzero first ASCII digit
  and at most fifteen total digits. Add no arbitrary minimum, trimming or local-number conversion. Reject extensions,
  separators, Unicode/whitespace, unprefixed short codes and alphanumeric sender IDs as values with DomainException.
  A short plus-prefixed sequence satisfying the grammar is accepted without assignment/reachability claims.
- Preserve all string-based SMS creation/accessor contracts. Consumers may adopt the new value at their own
  boundaries with explicit toString() conversion; SMS sender IDs, short codes and existing non-E.164 strings do not
  become invalid merely because E164PhoneNumber exists.
- Exclude CIDR/subnet/routing policy, DNS/email/phone reachability, number assignment databases, application
  identity policy, automatic SMS migration and changed email equality.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer extracts parts from an accepted quoted email | N/A; value access | N/A; local parsing | N/A; no domain mutation | Correct local and domain parts with unchanged address equality. |
| Consumer parses IPv4, IPv6 or equivalent IPv6 spellings | N/A; value construction | N/A; no network read | N/A; no domain mutation | Correct final family, canonical representation and equal values for equivalent spellings. |
| Consumer parses an IPv4-mapped IPv6 address | N/A; value construction | N/A; no network read | N/A; no domain mutation | Retain IPv6 identity rather than converting to IPv4. |
| Consumer parses an E.164 phone number or invalid alternative | N/A; value construction | N/A; no provider read | N/A; no domain mutation | Valid lexical value or predictable rejection, without reachability claims. |
| Existing SMS consumer uses strings, sender IDs or short codes | Existing consumer SMS workflow; no new command type | N/A; no new query type | N/A; no new event type | Existing message addressing contracts remain usable; no send is initiated by construction. |

Value construction/access performs no network lookup or delivery and requires no persistence/schema change.
These values describe addresses; they do not grant permission to contact an address or expose an identity.

## Validation and permissions

Validate lexical structure at the owning value, with stable supported exception families and representations.
Cover wrong-family and malformed inputs without silent coercion. Consumer authentication, canonical login identity,
contact permission, sender selection and provider rules remain outside scope; valid syntax is not policy approval.

## Dependencies and sequencing

Reuse existing Value/ValueObject contracts and EmailAddress/SMS boundaries. The approved three TASKs are independent:
TASK-00131 repairs email extraction; TASK-00132 adds the IP hierarchy; TASK-00133 adds the phone value. Each owns a
complete PR and its evidence, with no mutual/cross-TICKET dependency or execution priority. Preserve current Validate/
Application rule contracts; no base-class prefactor, automatic caller migration or terminal-evidence TASK is needed.

## Acceptance and evidence

- Begin the email repair with a failing quoted-local-part regression; cover ordinary and quoted extraction,
  accepted-address equality/canonicalization and relevant rejection paths.
- Cover representative valid/invalid IPv4 and IPv6 inputs, generic family selection, family-specific rejection,
  equivalent IPv6 spellings, IPv4-mapped IPv6, equality/hash and stable round trips.
- Cover valid phone lexical forms, one-digit lexical lower edge/fifteen-digit success/sixteen-digit rejection,
  extensions, unprefixed short codes, sender IDs, leading zero, Unicode/whitespace and malformed forms. Preserve
  string-based SMS construction/accessors and prove optional adoption without implicit sending; controlled provider
  translation is not live delivery or provider-acceptance qualification.
- Classify new public contracts and existing behavioral effects under
  [ADR 0009](../adr/0009-public-api-manifest-baseline.md),
  [ADR 0010](../adr/0010-behavioral-contract-authority.md) and
  [ADR 0011](../adr/0011-non-structural-compatibility-policy.md).
- Document parsing/normalization, family semantics, lexical-only guarantees and optional adoption. Each implementation
  TASK owns the complete `./bin/build` gate, exact owned-production statement coverage and independent review.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00131](../tasks/00131-TASK.md) | Correct Quoted Email Part Extraction | done |
| [TASK-00132](../tasks/00132-TASK.md) | Add Canonical IP Address Values | done |
| [TASK-00133](../tasks/00133-TASK.md) | Add Lexical E.164 Phone Numbers | done |
<!-- /planning:children -->

## Decisions and progress

2026-10-06: John approved the IP hierarchy and phone addition while retaining SMS strings and email equality in
[EPIC-00008](../epics/00008-EPIC.md), then approved this requirement area in its nine-TICKET split.
At that requirement checkpoint the TICKET was ready for TASK decomposition only, without implementation or
publication authority. Child status is generated above; [planning conventions](../CONVENTIONS.md) govern parent completion.

2026-10-06: John selected this TICKET and approved three complete independent PR-sized TASKs and their contracts:

- [TASK-00131 — Correct Quoted Email Part Extraction](../tasks/00131-TASK.md), a regression-first bug repair preserving
  quote/escape bytes, bracket stripping, original-string identity and separate canonicalization.
- [TASK-00132 — Add Canonical IP Address Values](../tasks/00132-TASK.md), a code-first additive family hierarchy with
  strict generic/concrete parsing, deterministic normalization and IPv4-mapped IPv6 identity.
- [TASK-00133 — Add Lexical E.164 Phone Numbers](../tasks/00133-TASK.md), a code-first additive lexical value with exact
  plus/ASCII/nonzero-first-digit/fifteen-digit bounds and explicit optional adoption through unchanged SMS strings.

Read-only probes reproduced incorrect quoted-email extraction, including escaped local parts/domain literals;
confirmed existing IP predicates accept equivalent mapped forms as IPv6 and reject contextual/ambiguous forms;
and retained short-code/sender-ID/non-E.164 SMS strings without sending. These are orientation/reproduction evidence,
not implementation regressions, a fresh full gate, coverage, provider acceptance or live delivery.

All three TASKs have no blockers/dependencies or assigned execution priority and own their own compatibility/docs/
full-gate/independent-review evidence. This TICKET is decomposed, not done; TICKET-00036 through TICKET-00039 still need
TASK decomposition. No new value or repair, implementation branch, worktree, commit, publication, merge,
certification, signing, release or deployment is authorized or claimed by planning.

Planning-only verification passed: `./bin/planning-check --write` and `./bin/planning-check` reported 173 records /
17 active; generated views were refreshed and tracked/new-record whitespace checks passed. No product test run,
fresh full gate, coverage, implementation acceptance or release certification is implied.
