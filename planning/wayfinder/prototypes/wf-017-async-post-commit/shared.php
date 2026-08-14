<?php

declare(strict_types=1);

namespace Prototype\AsyncPostCommit;

use CodeIgniter\Queue\Payloads\Payload;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\DatabaseQueue;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Throwable;

const QUEUE_NAME = 'realtime';
const JOB_NAME = 'access.user-deleted.realtime';
const USERS_TOPIC = 'access.users.page';

final readonly class QueuedRealtimeEvent implements ShouldQueue
{
    /** @param array<string, scalar> $metadata */
    public function __construct(
        public string $messageId,
        public string $occurredAt,
        public string $userId,
        public string $internalReason,
        public array $metadata,
        public bool $committed,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'occurred_at' => $this->occurredAt,
            'user_id' => $this->userId,
            'internal_reason' => $this->internalReason,
            'metadata' => $this->metadata,
            'committed' => $this->committed,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['message_id'],
            (string) $data['occurred_at'],
            (string) $data['user_id'],
            (string) $data['internal_reason'],
            (array) $data['metadata'],
            (bool) $data['committed'],
        );
    }
}

final class Mutation
{
    public bool $deleted = false;
    public bool $auditWritten = false;
    public bool $committed = false;

    public function transact(bool $fail = false): ?QueuedRealtimeEvent
    {
        $this->deleted = true;
        $this->auditWritten = true;

        if ($fail) {
            $this->deleted = false;
            $this->auditWritten = false;

            return null;
        }

        $this->committed = true;

        return new QueuedRealtimeEvent(
            '018f4f5a-2266-7d1f-b965-a783bbd5c102',
            '2026-08-14T12:45:00.123456Z',
            'internal-user-id',
            'private deletion reason',
            ['correlation_id' => 'corr-017', 'trace_id' => 'private-trace'],
            true,
        );
    }
}

final class RecordingPrivatePublisher
{
    /** @var list<array{topic: string, message: string}> */
    public array $published = [];
    public int $attempts = 0;

    public function __construct(private bool $failFirstAttempt = false) {}

    public function pushPrivate(string $topic, string $message): void
    {
        ++$this->attempts;
        if ($this->failFirstAttempt && $this->attempts === 1) {
            throw new RuntimeException('prototype transport unavailable');
        }

        $this->published[] = ['topic' => $topic, 'message' => $message];
    }
}

final readonly class UserDeletedRealtimeSubscriber
{
    public function __construct(private RecordingPrivatePublisher $publisher) {}

    /** @return array<string, mixed> */
    public function receive(QueuedRealtimeEvent $event): array
    {
        expect($event->committed, 'Subscriber must never receive an uncommitted event.');

        $envelope = [
            'message_id' => $event->messageId,
            'event_name' => 'access.user.deleted',
            'schema_version' => 1,
            'occurred_at' => $event->occurredAt,
            'topic' => USERS_TOPIC,
            'payload' => ['invalidate' => USERS_TOPIC],
            'metadata' => array_intersect_key($event->metadata, ['correlation_id' => true]),
        ];
        $message = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        expect(!str_contains($message, $event->userId), 'Public envelope leaked the domain User identity.');
        expect(!str_contains($message, $event->internalReason), 'Public envelope leaked the private reason.');
        expect(!str_contains($message, 'private-trace'), 'Public envelope leaked arbitrary metadata.');

        $this->publisher->pushPrivate(USERS_TOPIC, $message);

        return $envelope;
    }
}

interface PrototypeQueue
{
    public function transport(): string;

    public function enqueue(QueuedRealtimeEvent $event): void;

    public function pending(): int;

    public function receive(): ?QueuedRealtimeEvent;

    public function acknowledge(): void;

    public function retry(): void;
}

final class SymfonyMessengerQueue implements PrototypeQueue
{
    private InMemoryTransport $transport;
    private ?Envelope $received = null;

    public function __construct(private readonly string $framework)
    {
        $this->transport = new InMemoryTransport(new PhpSerializer());
    }

    public function transport(): string
    {
        return $this->framework . ': symfony/messenger serialized transport';
    }

    public function enqueue(QueuedRealtimeEvent $event): void
    {
        $this->transport->send(new Envelope($event));
    }

    public function pending(): int
    {
        return count(iterator_to_array($this->transport->get()));
    }

    public function receive(): ?QueuedRealtimeEvent
    {
        $this->received = iterator_to_array($this->transport->get())[0] ?? null;

        return $this->received?->getMessage();
    }

    public function acknowledge(): void
    {
        expect($this->received !== null, 'No Symfony Messenger envelope is reserved.');
        $this->transport->ack($this->received);
        $this->received = null;
    }

    public function retry(): void
    {
        $this->received = null;
    }
}

final class LaravelDatabaseQueue implements PrototypeQueue
{
    private DatabaseQueue $queue;
    private mixed $received = null;

    public function __construct()
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $connection = $capsule->getConnection();
        $connection->getSchemaBuilder()->create('jobs', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $this->queue = new DatabaseQueue($connection, 'jobs', QUEUE_NAME, 60, false);
        $this->queue->setContainer(new Container());
    }

    public function transport(): string
    {
        return 'laravel: illuminate/queue database transport';
    }

    public function enqueue(QueuedRealtimeEvent $event): void
    {
        $this->queue->push($event, queue: QUEUE_NAME);
    }

    public function pending(): int
    {
        return $this->queue->size(QUEUE_NAME);
    }

    public function receive(): ?QueuedRealtimeEvent
    {
        $this->received = $this->queue->pop(QUEUE_NAME);
        if ($this->received === null) {
            return null;
        }

        $payload = json_decode($this->received->getRawBody(), true, flags: JSON_THROW_ON_ERROR);
        $command = unserialize(
            (string) $payload['data']['command'],
            ['allowed_classes' => [QueuedRealtimeEvent::class]],
        );
        expect($command instanceof QueuedRealtimeEvent, 'Laravel queue did not restore the portable event job.');

        return $command;
    }

    public function acknowledge(): void
    {
        expect($this->received !== null, 'No Laravel queue job is reserved.');
        $this->received->delete();
        $this->received = null;
    }

    public function retry(): void
    {
        expect($this->received !== null, 'No Laravel queue job is reserved.');
        $this->received->release(0);
        $this->received = null;
    }
}

final class CodeIgniterQueuePayload implements PrototypeQueue
{
    /** @var list<string> */
    private array $jobs = [];

    public function transport(): string
    {
        return 'codeigniter: codeigniter4/queue v1 Payload contract with project worker storage';
    }

    public function enqueue(QueuedRealtimeEvent $event): void
    {
        $payload = (new Payload(JOB_NAME, $event->toArray()))->setQueue(QUEUE_NAME);
        $this->jobs[] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function pending(): int
    {
        return count($this->jobs);
    }

    public function receive(): ?QueuedRealtimeEvent
    {
        if ($this->jobs === []) {
            return null;
        }

        $payload = Payload::fromArray(json_decode($this->jobs[0], true, flags: JSON_THROW_ON_ERROR));
        expect($payload->getJob() === JOB_NAME, 'CodeIgniter job name drifted.');
        expect($payload->getQueue() === QUEUE_NAME, 'CodeIgniter queue name drifted.');

        return QueuedRealtimeEvent::fromArray($payload->getData());
    }

    public function acknowledge(): void
    {
        array_shift($this->jobs);
    }

    public function retry(): void
    {
        // Leave the reserved prototype payload in place for the next bounded attempt.
    }
}

final class YiiForwardCompatibleQueue implements PrototypeQueue
{
    /** @var list<string> */
    private array $jobs = [];

    public function transport(): string
    {
        return 'yii: project JSON transport behind a replaceable queue seam';
    }

    public function enqueue(QueuedRealtimeEvent $event): void
    {
        $this->jobs[] = json_encode([
            'type' => JOB_NAME,
            'queue' => QUEUE_NAME,
            'data' => $event->toArray(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function pending(): int
    {
        return count($this->jobs);
    }

    public function receive(): ?QueuedRealtimeEvent
    {
        if ($this->jobs === []) {
            return null;
        }

        $payload = json_decode($this->jobs[0], true, flags: JSON_THROW_ON_ERROR);
        expect($payload['type'] === JOB_NAME, 'Yii transport job name drifted.');

        return QueuedRealtimeEvent::fromArray($payload['data']);
    }

    public function acknowledge(): void
    {
        array_shift($this->jobs);
    }

    public function retry(): void
    {
        // Leave the prototype message pending for the next bounded attempt.
    }
}

final readonly class PostCommitProducer
{
    public function __construct(private PrototypeQueue $queue) {}

    public function delete(bool $fail = false): Mutation
    {
        $mutation = new Mutation();
        $event = $mutation->transact($fail);
        if ($event !== null) {
            expect($mutation->committed, 'Queue dispatch must happen only after commit.');
            $this->queue->enqueue($event);
        }

        return $mutation;
    }
}

final class PrototypeWorker
{
    public int $handled = 0;
    public int $failed = 0;

    public function __construct(
        private readonly PrototypeQueue $queue,
        private readonly UserDeletedRealtimeSubscriber $subscriber,
    ) {}

    /** @return array<string, mixed>|null */
    public function runOne(): ?array
    {
        $event = $this->queue->receive();
        if ($event === null) {
            return null;
        }

        try {
            $envelope = $this->subscriber->receive($event);
            $this->queue->acknowledge();
            ++$this->handled;

            return $envelope;
        } catch (Throwable $throwable) {
            $this->queue->retry();
            ++$this->failed;

            throw $throwable;
        }
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, string> */
function lockedVersions(): array
{
    $lock = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    $versions = [];
    foreach ($lock['packages'] as $package) {
        $versions[$package['name']] = $package['version'];
    }

    return $versions;
}

/** @param array<string, mixed> $receipt */
function writeReceipt(string $framework, array $receipt): void
{
    $directory = __DIR__ . '/receipts';
    if (!is_dir($directory)) {
        mkdir($directory, recursive: true);
    }
    file_put_contents(
        $directory . '/' . $framework . '.json',
        json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
}
