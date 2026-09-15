# Testing and completion evidence

Features: write testable code first, then tests. Bugs: reproduce locally, capture useful evidence, add one meaningful failing regression test proving the defect, then repair it. Verify that the initial failure is the expected bug rather than broken test setup. Keep the regression test and add coverage for the finished behavior. If reproduction is blocked, report why; never fabricate a red result.

Aim for 100% coverage of owned production lines/statements through unit tests first, with appropriate mocks. Use meaningful adapter integration/functional tests when boundaries need proof. Follow the project's exact coverage gate and metadata. Resolve frontend metrics/exemptions explicitly; do not silently assert an unconfigured metric passed.

Choose collaborators according to what a test proves. A handler unit test may mock a specification. A specification may test against controlled repositories/services. Container integration may resolve them together with a mocked database boundary; label that as composition proof, not real database integration. Prefer real value objects, but do not score a test arrangement merely because the reviewer prefers different mocks.

Cover failure paths and business effects, not only successful calls. Use project fixtures/builders, test naming, namespaces, and assertion conventions. Avoid tests that mirror implementation or mechanically inspect text.

## Gate boundaries

Use focused checks while iterating. Before a commit or PR, run the complete canonical product gate for the final content: tests, exact coverage, style, static analysis, architecture checks, docs/planning validation, and frontend build/type checking as applicable. Hooks must succeed; never bypass them to claim a completed gate.

Release/tooling tests live outside product coverage and do not run in ordinary CI or the default build. Run separate explicit qualification when tooling changes warrant it. Do not add tests for Markdown, wrapper/config text, generated files, or planning validators to the product suite. Directly validate these artifacts instead. A feature does not inherit a fake-SSH or long deployment rehearsal suite.

Surface warnings, notices, deprecations, and non-failing advisories. Fold easy relevant fixes into the current TASK; record larger issues and their impact. Do not quietly disable checks or suppress newly introduced failures. A passing local build is distinct from hosted CI, release certification, and production qualification. Unavailable, skipped, or unreadable hosted evidence is not a pass; follow the project's documented policy or obtain the missing decision.

## Evidence

Capture before and after screenshots where the behavior is visible, including a reproduced bug where feasible. For APIs/libraries, use request/response transcripts or executable behavior proof. Record scenario, environment/URL, timestamp, revision, expected result, and actual result. Sanitize credentials and personal data. If no useful visual exists, explain and use the better evidence.

Store logs and captures under the TASK's ignored run directory. Include accessible screenshots in the PR when possible; local paths are not images accessible to remote reviewers. Record limitations without implying an upload happened. Preserve the chronology of failures and subsequent focused/full reruns. A detached run needs its final exit status and complete log; partial output is not completion evidence.
