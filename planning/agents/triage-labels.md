# Triage States

- `needs-triage`: not yet classified.
- `needs-info`: blocked on a decision or missing evidence.
- `ready-for-agent`: decision-complete and executable when dependencies are done.
- `ready-for-human`: requires human judgment or an external action.
- `in-progress`: actively being changed.
- `done`: TASK implementation acceptance and required local verification are complete, before publication;
  pending independent review and delivery are recorded separately. Parents close through [automatic parent completion](../CONVENTIONS.md#automatic-parent-completion).
- `wontfix`: intentionally closed without implementation.

Do not store `blocked`; derive it from unfinished dependency edges. A green build alone does not establish
acceptance. Follow [Planning Conventions](../CONVENTIONS.md) rather than inferring review or delivery authority
from a terminal status.
