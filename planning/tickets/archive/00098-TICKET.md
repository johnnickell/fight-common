---
id: T-00098
prd: PRD-00022
title: Accept the Initial Documentation Release Candidate
status: done
blocked_by:
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

- [x] A clean checkout produces the strict documentation render; direct inspection confirms the canonical routes,
      navigation, search, assets, metadata, sitemap, and custom 404 beneath `/fight-common/`.
- [x] Homepage, README, Quick Start, Architecture, Mail, Messaging, and representative remaining component and
      framework guides are editorially accurate and aligned with public source and support contracts.
- [x] John resizes the representative pages in Brave through narrow mobile, tablet, and wide desktop layouts and
      accepts their hierarchy, navigation, diagrams, tabs, controls, code, and lack of page-level overflow.
- [x] One keyboard and screen-reader smoke check proves landmarks, headings, skip navigation, visible and
      unobscured focus, search, menu, theme, copy, tabs, links, and state announcements.
- [x] Light and dark themes meet WCAG 2.2 AA contrast, retain color-independent meaning, and preserve 44px target,
      reduced-motion, 200% zoom, reflow, and readable scrolling-code behavior.
- [x] A simple baseline records generated HTML, CSS, JavaScript, font, image, and search-index sizes without
      imposing a Lighthouse or Core Web Vitals gate.
- [x] Pull-request checks remain fast and deterministic and contain no browser, screenshot, or performance-score
      suite.
- [x] The evidence explicitly describes the candidate as predeployment and does not claim hosted Pages success.
- [x] The canonical `./bin/build` passes before the candidate is committed or offered for pull-request review.

## Verification

- Run the clean-checkout strict MkDocs build and inspect the resulting site directly. Do not add tests for
  documentation content, generated files, browser presentation, configuration text, or documentation tooling.
- Check examples against current source and existing production-code tests, then run `./bin/planning-check`,
  `git diff --check`, and `./bin/build`; PHPUnit remains limited to production code.
- Record John's focused Brave review and the keyboard/screen-reader smoke-check outcome separately from automation.

## Completion Notes

Accepted the complete predeployment documentation candidate pinned at
`098d18f6fe5bde87a29fd7af3d63f78433efbba3` on 2026-09-11. Refinement round one corrected the HTTP Client test
container example without replacing the Fight adapter, clarified Messaging timestamp precision and recursive
`Meta` values, and removed redundant snippet-source H1 headings from eleven generated component pages. No PHP
public API, runtime behavior, browser suite, screenshot suite, or tooling test changed, and no generated output
was tracked or committed.

`./bin/docs validate` passed after repair and confirmed canonical routes, navigation, links, search, assets,
unique metadata and anchors, sitemap, tabs, copy controls, and the custom 404 beneath `/fight-common/`. The final
generated baseline is 36 HTML files / 3,045,806 bytes; 3 CSS / 192,030; 38 JavaScript / 1,065,529; 3 fonts /
78,600; 34 images / 325,309; and `search/search_index.json` / 505,644 bytes (126 files / 6,531,306 bytes total).
Editorial review reconciled the homepage, README, Quick Start, Architecture, component guides, operations,
framework support, and CodeIgniter guidance with repository source, Composer metadata, support policy, and
existing behavioral evidence.

Automated browser preflight found one H1, no duplicate IDs, and no page-level overflow on Homepage, Quick Start,
Architecture, Mail, and Messaging at 375px, 768px, and 1440px. A 320-CSS-pixel reflow check contained all eleven
Quick Start code blocks in their own scrollers; visible header controls measured 44px; reduced-motion rules were
present; and light/dark theme, mobile menu, search, keyboard tabs, and skip-navigation behavior passed. John
separately accepted the Brave responsive/theme journey and the Brave/VoiceOver keyboard and screen-reader smoke
journey, including contrast, color-independent meaning, focus, announcements, 200% zoom, and reflow.

The required persistent `screen` build wrote `0` to its exit file. `./bin/build` passed all quality stages with
3,641 tests, 20,637 assertions, and exact 10,089/10,089 statement coverage, including planning validation after
the final acceptance transition. This is local predeployment acceptance only: no `develop` to `main` publication,
protected Pages deployment, or hosted-success claim occurred. T-00099 remains the distinct verification target
after that separately authorized publication.
