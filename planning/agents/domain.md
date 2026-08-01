# Domain and Engineering Rules

Preserve the dependency direction Domain <- Application <- Adapter. Event-sourced aggregates and storage contracts remain framework-free. Durable DBAL implementations belong in Adapter.

Use explicit event application: aggregate `apply(Event $event)` routes to semantic `when*()` methods without reflection. Stored events use stable aliases and integer schema versions. Projectors are idempotent because delivery is at least once.

All production classes require complete test coverage. Run the non-interactive Docker submit gate documented in `CLAUDE.md`; never invoke the interactive `./bin/php*` wrappers from an agent.
