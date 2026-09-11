---
id: T-00099
prd: PRD-00022
title: Verify the First Hosted Pages Publication
status: done
blocked_by:
---

# Verify the First Hosted Pages Publication

## Outcome

After John separately authorizes and performs the `develop` to `main` transition and the protected Pages workflow
deploys, verify the real public Fight Common site and establish its grouped routes as compatibility commitments.

## Scope

- In scope: hosted workflow result, public homepage and representative routes, assets, navigation, search,
  themes, metadata, sitemap, custom 404, `/fight-common/` behavior, publication evidence, and route-compatibility
  handoff.
- Out of scope: authorizing or performing the merge, bypassing the protected environment, changing content during
  verification, publishing the profile adaptation, or treating predeployment evidence as hosted success.

## Acceptance Criteria

- [x] John has separately authorized and performed the intended `develop` to `main` merge; this ticket does not
      infer or execute that effect.
- [x] The protected GitHub Pages workflow completed successfully from the intended `main` commit and deployed the
      matching generated artifact.
- [x] The public homepage, Quick Start, Architecture, Mail, Messaging, representative component and framework
      routes, and repository links resolve beneath `/fight-common/` without broken or root-relative assets.
- [x] Hosted navigation, search, theme selection, copy controls, configuration tabs, article anchors, fonts,
      favicons, and social image behave as intended.
- [x] Canonical metadata, sitemap membership, and the custom 404 point readers back into the Fight Common project
      base correctly.
- [x] Any hosted-only failure is repaired and redeployed through normal reviewed source changes; verification
      never patches generated production output directly.
- [x] The published grouped routes and important linked anchors are recorded as compatibility commitments for
      future changes.
- [x] Hosted evidence is recorded separately from local, pull-request, and predeployment results.

## Verification

- Inspect the exact hosted workflow and Pages deployment result for the intended commit.
- Exercise the public routes, assets, navigation, search, themes, metadata, sitemap, and 404 over HTTPS.
- Run `./bin/planning-check` after recording the final planning outcome.

## Completion Notes

John separately authorized publication, and PR #144 merged the pinned `develop` head
`9c5b131ece9c110479ca52c46feb47ad893bf166` into the pinned `main` base
`50a04cec50d1fba8057b042115923818169d31cc`. The resulting merge commit is
`40f347f39389dfd43dbfcef132cbb65e824747f1`. The exact-SHA Tests run `34644736852` passed, and Deploy Docs run
`34644736846` passed documentation validation, Pages artifact upload, and protected-environment deployment.
Pages deployment `6401263441` reports `success` for that exact merge SHA and the public
`https://johnnickell.github.io/fight-common/` environment URL. The read-back publishing state is the workflow
source with HTTPS enforced and a single `main` deployment-branch policy.

Hosted verification was performed over HTTPS against the deployed site, separately from T-00098's local and
predeployment acceptance. The homepage and all 34 non-home canonical routes returned `200`; the repository and
license links also returned `200`. Search returned the Mail guide and navigated to its canonical route. Light and
dark theme selection persisted across navigation. Pointer and arrow-key configuration-tab selection, the active
format's copy control and visible copy confirmation, article anchors, wide and 390px layouts, page-level overflow,
semantic regions, images, and browser console health all passed. The emitted CSS, JavaScript, search index, Fight
marks, favicon family, social image, and three self-hosted fonts returned `200`. Canonical metadata stayed beneath
`/fight-common/`; the valid sitemap contained the complete canonical inventory and no outside-base URL; and a
missing project route returned the branded custom page with HTTP `404` and a working project-home link. No
hosted-only defect was found, so no source repair, generated-output patch, or redeployment was required.

The first publication establishes these compatibility commitments:

- Entry routes: `/fight-common/`, `/fight-common/quick-start/`, and `/fight-common/architecture/`.
- Model the Domain: `values`, `collections`, `specifications`, `repositories`, `event-sourcing`, and `utilities`
  beneath `/fight-common/components/`.
- Coordinate Application Behavior: `messaging`, `validation`, `serialization`, and `dependency-injection`
  beneath `/fight-common/components/`.
- Connect Systems: `http-client`, `auth`, `cache`, `files`, `file-transfer`, `templating`, `routing`, `mail`, `sms`,
  and `sockets` beneath `/fight-common/components/`.
- Operate Workloads: `observability`, `process`, and `scheduler` beneath `/fight-common/components/`.
- Frameworks: `framework-support`, `framework-free`, `symfony`, `laravel`, `yii`, `codeigniter`, and `slim`
  beneath `/fight-common/frameworks/`.
- Maintenance: `/fight-common/maintenance/contributing/` and `/fight-common/maintenance/coding-standard/`.
- Important linked anchors include Quick Start `#pick-your-framework` and `#wire-the-application`; Architecture
  `#the-dependency-rule`, `#ports-and-adapters`, and `#cqrs-message-flows`; Mail `#configuration-formats` and
  `#supported-delivery-paths`; Messaging `#async-delivery`, `#message-primitives`, and `#commands`; Framework
  Support `#support-window`, `#capability-matrix`, and `#starter-support-receipt-v1`; Framework Free
  `#the-portable-baseline` and `#provider-and-operations-boundaries`; Symfony `#installation-and-ownership` and
  `#application-owned-operations`; Laravel `#installation-and-ownership` and `#application-owned-operations`;
  Yii `#installation-and-ownership` and `#application-owned-operations`; CodeIgniter `#ownership-and-installation`
  and `#operational-ownership`; and Slim `#installation-and-ownership` and `#application-owned-operations`.

Future removal or renaming of these published routes or anchors requires a working alias or redirect.

After the final planning synchronization, `./bin/planning-check` passed with 122 records and 13 active records.
