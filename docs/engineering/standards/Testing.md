# Testing and completion evidence

Features: write testable code first, then tests. Bugs: reproduce locally, capture useful evidence, add one meaningful failing regression test proving the defect, then repair it. Verify that the initial failure is the expected bug rather than broken test setup. Keep the regression test and add coverage for the finished behavior. If reproduction is blocked, report why; never fabricate a red result.

Aim for 100% coverage of owned production lines/statements through unit tests first, with appropriate mocks. Use meaningful adapter integration/functional tests when boundaries need proof. Follow the project's exact coverage gate and metadata. Resolve frontend metrics/exemptions explicitly; do not silently assert an unconfigured metric passed.

Choose collaborators according to what a test proves. A handler unit test may mock a specification. A specification may test against controlled repositories/services. Container integration may resolve them together with a mocked database boundary; label that as composition proof, not real database integration. Prefer real value objects, but do not score a test arrangement merely because the reviewer prefers different mocks.

Cover failure paths and business effects, not only successful calls. Use project fixtures/builders, test naming, namespaces, and assertion conventions. Avoid tests that mirror implementation or mechanically inspect text.

## Gate boundaries

Use focused checks while iterating. Before a commit or PR, run the complete canonical product gate for the final content: tests, exact coverage, style, static analysis, architecture checks, docs/planning validation, and frontend build/type checking as applicable. Hooks must succeed; never bypass them to claim a completed gate.

**Implementation scope:** the builder must run the full local pre-submit gate before publication, retain the result and report it. After commit/push/PR creation, hosted CI is not a builder completion gate: do not scan workflow runs, poll checks, wait for completion or fetch hosted logs unless explicitly asked to investigate or monitor CI. Verify publication and PR evidence normally, and label hosted CI “not checked by this build”; do not imply a pass. Internal implementation review follows this same scope. Independent review and landing retain their applicable hosted-check requirements; intentionally leaving those to that stage is not itself an implementation defect.

Release/tooling tests live outside product coverage and do not run in ordinary CI or the default build. Run separate explicit qualification when tooling changes warrant it. Do not add tests for Markdown, wrapper/config text, generated files, or planning validators to the product suite. Directly validate these artifacts instead. A feature does not inherit a fake-SSH or long deployment rehearsal suite.

Surface warnings, notices, deprecations, and non-failing advisories. Fold easy relevant fixes into the current TASK; record larger issues and their impact. Do not quietly disable checks or suppress newly introduced failures. A passing local build is distinct from hosted CI, release certification, and production qualification. Unavailable, skipped, or unreadable hosted evidence is not a pass; follow the project's documented policy or obtain the missing decision.

## Evidence

**Explicit exclusion — planning-only sessions:** authoring or updating maps, EPICs, TICKETs, TASKs, Boards and other planning records requires no screenshots or Before/After section. Verify the records, links, dependencies and generated views as applicable, and summarize that verification in the handoff or PR. This exclusion does not waive an explicit screenshot request or evidence for implementation included in the same session.

For visible changes, capture a before screenshot before editing and an after screenshot demonstrating the same scenario after implementation. Rendered documentation and Boards count as visible changes outside the planning-only exclusion above. Include a reproduced bug where feasible. Keep viewport, scenario and relevant state comparable; explain intentional differences. For APIs/libraries with no useful visual, use request/response transcripts or executable behavior proof and explain why that evidence is appropriate.

Record scenario, environment/URL, capture timestamp, source revision, expected result and actual result. If the work is already implemented, a verified prior revision or saved baseline may be rendered retrospectively; identify its source and actual capture time. A screenshot of the new state is not before evidence. Sanitize credentials and personal data.

Store logs and captures under the TASK's ignored run directory. Outside that exclusion, the PR must have a labeled **Before/After evidence** section containing the useful screenshots, embedded through attachments or an approved location accessible to its intended reviewers. Provide captions tying each image to the behavior and revision. Keep `.runs` ignored; do not stage scratch evidence or add images to product assets merely to obtain a URL.

If screenshots add no useful proof, state the reason in that section and include the nonvisual evidence. If capture or upload is blocked, state the specific obstacle, available local artifacts, useful accessible alternative and remaining human step in both the PR and handoff. “When possible” is not permission for silent omission. Required acceptance proof that is still missing remains unverified; recording an obstacle does not make it pass.

After creating or updating the PR, read back its published description and verify that the evidence links resolve and images render for the intended audience. A local filesystem path, saved screenshot, or unexecuted upload instruction does not prove publication. Preserve the chronology of failures and subsequent focused/full reruns. A detached run needs its final exit status and complete log; partial output is not completion evidence.
