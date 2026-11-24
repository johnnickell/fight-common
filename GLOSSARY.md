# Fight Common glossary

Fight Common supplies reusable PHP contracts and adapters. These terms describe the library; consuming applications name their own business concepts.

## Messaging

- **Payload**: The command, query or event data carried by a message, not the envelope that carries it.
- **Message**: The envelope that pairs a payload with its identity, creation timestamp and isolated metadata. For an event, the envelope timestamp is not necessarily when the domain fact occurred.
- **Message metadata**: Application, transport, audit or observability context attached to an envelope, not domain state. See [metadata isolation](docs/messaging.md#message-metadata-isolation).
- **Complete event fan-out**: Synchronous dispatch that attempts every matching handler before reporting collected failures. See [dispatch order and failures](docs/messaging.md#complete-event-fan-out).
- **Queued Fight message delivery**: At-least-once delivery of a complete command or event envelope to a handler that calls the synchronous Fight bus or dispatcher. See [delivery boundaries](docs/messaging.md#delivery-and-ownership-boundaries).

## Domain and persistence

- **Aggregate root**: The entity that owns an aggregate's identity, invariants and state transitions. It need not be event-sourced; the [Event Sourcing base](docs/event-sourcing.md) is specific to that capability.
- **Stored event**: The durable record of an event message in a stream, including its stream position and stable identity. It is not the domain payload alone.
- **Stream identifier**: The exact pair of aggregate name and aggregate identifier that names an event stream. See [Event Sourcing](docs/event-sourcing.md).
- **Unit of work**: The transaction boundary that runs an operation; it is not the repository that loads or saves records. See [transaction ownership](docs/engineering/standards/Architecture.md#transactions-and-events).

## Files

- **File Storage**: Backend-neutral access to file contents, whether stored locally or remotely. See [Files](docs/files.md).
- **Filesystem**: Operations on the local operating system's files and directories. See [Files](docs/files.md).
- **File Transfer**: Sending, retrieving and listing remote entries through a named transport. A File Transfer resource describes a remote entry, not an MCP Resource. See [File Transfer](docs/file-transfer.md).

## MCP execution and delivery

- **Execution observation**: Retained evidence of an execution lifecycle fact, not a request to run work or authority to control it. See [Execution observations](docs/execution-observations.md).
- **Durable Task**: The authoritative record of asynchronous work that can be inspected and advanced after the initiating request ends. See [Durable Tasks](docs/durable-tasks.md).
- **Live subscription**: A request-scoped channel for eligible live MCP notifications, not a durable recovery feed. See [MCP subscriptions](docs/mcp-subscriptions.md).
- **Retained safe Notice**: A bounded, safe record an authorized client can recover independently of live delivery. It is not the underlying Task state or execution observation. See [Safe Notices](docs/safe-notices.md).
- **MCP Resource**: URI-addressed content exposed through an explicitly composed provider and consumer availability policy. An MCP Resource URI is not a filesystem path or an access grant. See [MCP](docs/components/mcp/index.md).
