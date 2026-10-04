# Issue Tracker

Resolve work from the canonical file in `planning/tasks/`, never from an inferred TICKET or GitHub number. Before implementation, confirm its acceptance criteria, dependencies, branch, seams, and verification commands.

Keep the task, board, TICKET, epic, and roadmap synchronized. A task is executable only when its status is `ready-for-agent` and every `blocked_by` task is terminal. Use `.runs/` for coordinate-build scratch and copy durable outcomes back into the task.
