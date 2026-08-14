# WF-017 asynchronous post-commit prototype

> **PROTOTYPE — wipeable queue-seam evidence, not a supported worker composition.**

## Question

Can one unchanged realtime Application subscriber run directly in focused synchronous tests and later through
each starter's selected queue boundary, while rolled-back work is never enqueued, committed work survives a
transport failure for retry, and public realtime output remains minimal?

## Run

From the Fight Common repository root, with the `fight-common` image already built:

```bash
docker run --rm -v "$(pwd):/app:delegated" -w /app fight-common \
  php planning/wayfinder/prototypes/wf-017-async-post-commit/run.php
```

The runner rewrites one machine-readable receipt per framework under `receipts/`.

## Candidate boundaries

- Symfony and Slim use Symfony Messenger's serialized in-memory transport as the bounded sender/receiver
  proof.
- Laravel uses Illuminate Queue's database transport against disposable SQLite and restores the queued job
  from its native payload.
- CodeIgniter uses the stable `codeigniter4/queue` v1 payload contract. The prototype deliberately does not
  boot a full CodeIgniter application or claim its database worker is proven.
- Yii retains a project-owned JSON transport seam because `yiisoft/queue` still has no stable release. The
  serialized job contract can move behind a future stable adapter without changing the subscriber.

## Deliberate limits

- Symfony Messenger's in-memory transport and the CodeIgniter/Yii candidate stores are not production-durable
  brokers. The prototype proves delayed serialized invocation, not deployment topology.
- The CodeIgniter database worker, a real Yii broker adapter, framework worker commands, failure stores,
  backoff, dead-letter policy, process supervision, and multi-worker races remain open.
- Queue publication is explicitly performed after the transaction returns. This does not provide atomic
  database-and-queue handoff; a crash in that gap can lose the invalidation. A durable outbox is not selected
  by this prototype.
- The public users-page event is an idempotent invalidation. A retry may attempt transport delivery more than
  once; the client still refetches authoritative state.
