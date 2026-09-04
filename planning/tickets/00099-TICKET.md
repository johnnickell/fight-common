---
id: T-00099
prd: PRD-00022
title: Verify the First Hosted Pages Publication
status: ready-for-agent
blocked_by: T-00098
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

- [ ] John has separately authorized and performed the intended `develop` to `main` merge; this ticket does not
      infer or execute that effect.
- [ ] The protected GitHub Pages workflow completed successfully from the intended `main` commit and deployed the
      matching generated artifact.
- [ ] The public homepage, Quick Start, Architecture, Mail, Messaging, representative component and framework
      routes, and repository links resolve beneath `/fight-common/` without broken or root-relative assets.
- [ ] Hosted navigation, search, theme selection, copy controls, configuration tabs, article anchors, fonts,
      favicons, and social image behave as intended.
- [ ] Canonical metadata, sitemap membership, and the custom 404 point readers back into the Fight Common project
      base correctly.
- [ ] Any hosted-only failure is repaired and redeployed through normal reviewed source changes; verification
      never patches generated production output directly.
- [ ] The published grouped routes and important linked anchors are recorded as compatibility commitments for
      future changes.
- [ ] Hosted evidence is recorded separately from local, pull-request, and predeployment results.

## Verification

- Inspect the exact hosted workflow and Pages deployment result for the intended commit.
- Exercise the public routes, assets, navigation, search, themes, metadata, sitemap, and 404 over HTTPS.
- Run `./bin/planning-check` after recording the final planning outcome.

## Completion Notes

Pending T-00098 and a separately authorized first publication.
