---
id: T-00098
prd: PRD-00022
title: Accept the Initial Documentation Release Candidate
status: ready-for-agent
blocked_by: T-00094,T-00095,T-00096,T-00097
---

# Accept the Initial Documentation Release Candidate

## Outcome

Prove from a clean checkout that the complete Fight Common documentation candidate is accurate, internally
coherent, accessible, responsive, and ready for John's separately authorized `develop` to `main` merge without
turning that focused launch review into a permanent slow browser suite.

## Scope

- In scope: clean documentation render, representative content accuracy,
  John's Brave resize review, lightweight keyboard and screen-reader smoke check, both themes, reduced motion,
  zoom and reflow, launch asset-size baseline, and recorded predeployment evidence.
- Out of scope: merging, deploying, hosted-success claims, routine cross-browser or viewport matrices,
  Lighthouse thresholds, screenshot regression, exhaustive assistive-technology certification, or profile work.

## Acceptance Criteria

- [ ] A clean checkout produces the strict documentation render; direct inspection confirms the canonical routes,
      navigation, search, assets, metadata, sitemap, and custom 404 beneath `/fight-common/`.
- [ ] Homepage, README, Quick Start, Architecture, Mail, Messaging, and representative remaining component and
      framework guides are editorially accurate and aligned with public source and support contracts.
- [ ] John resizes the representative pages in Brave through narrow mobile, tablet, and wide desktop layouts and
      accepts their hierarchy, navigation, diagrams, tabs, controls, code, and lack of page-level overflow.
- [ ] One keyboard and screen-reader smoke check proves landmarks, headings, skip navigation, visible and
      unobscured focus, search, menu, theme, copy, tabs, links, and state announcements.
- [ ] Light and dark themes meet WCAG 2.2 AA contrast, retain color-independent meaning, and preserve 44px target,
      reduced-motion, 200% zoom, reflow, and readable scrolling-code behavior.
- [ ] A simple baseline records generated HTML, CSS, JavaScript, font, image, and search-index sizes without
      imposing a Lighthouse or Core Web Vitals gate.
- [ ] Pull-request checks remain fast and deterministic and contain no browser, screenshot, or performance-score
      suite.
- [ ] The evidence explicitly describes the candidate as predeployment and does not claim hosted Pages success.
- [ ] The canonical `./bin/build` passes before the candidate is committed or offered for pull-request review.

## Verification

- Run the clean-checkout strict MkDocs build and inspect the resulting site directly. Do not add tests for
  documentation content, generated files, browser presentation, configuration text, or documentation tooling.
- Check examples against current source and existing production-code tests, then run `./bin/planning-check`,
  `git diff --check`, and `./bin/build`; PHPUnit remains limited to production code.
- Record John's focused Brave review and the keyboard/screen-reader smoke-check outcome separately from automation.

## Completion Notes

T-00091 completed the validated and browser-qualified repository entry surface. T-00092 completed the
executable framework-neutral journey and focused Brave qualification. T-00093 completed the approved Hexagonal
Architecture and CQRS guide. T-00094 completed the Mail reference component guide; T-00095 through T-00097 remain
pending.
