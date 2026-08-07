# Issue Tracker

Resolve work from the canonical file in `planning/tickets/`, never from an inferred PRD or GitHub number. Before implementation, confirm its acceptance criteria, dependencies, branch, seams, and verification commands.

Keep the ticket, board, PRD, epic, and roadmap synchronized. A ticket is executable only when its status is `ready-for-agent` and every `blocked_by` ticket is terminal. Use `.runs/` for coordinate-build scratch and copy durable outcomes back into the ticket.
