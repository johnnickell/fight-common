# Triage States

- `needs-triage`: not yet classified.
- `needs-info`: blocked on a decision or missing evidence.
- `ready-for-agent`: decision-complete and executable when dependencies are done.
- `ready-for-human`: requires human judgment or an external action.
- `in-progress`: implementation, revision or required independent Review/QA is underway.
- `done`: TASK implementation acceptance, required verification, independent Review acceptance and required
  QA are satisfied (including justified overall N/A); delivery is separate. Pending Review/QA stays non-terminal
  with an explicit handoff. Preserve historical completion meanings. Parents close through [automatic parent completion](../CONVENTIONS.md#automatic-parent-completion).
- `wontfix`: intentionally closed without implementation.

Do not store `blocked`; derive it from unfinished dependency edges. A green build alone does not establish
acceptance. Follow [Planning Conventions](../CONVENTIONS.md) rather than inferring review or delivery authority
from a terminal status.
