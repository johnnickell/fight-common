<?php

declare(strict_types=1);

namespace Prototype\RealtimePublication;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

const USERS_TOPIC_FAMILY = 'access.users.page';
const MERCURE_USERS_TOPIC = 'https://starter.example.test/topics/access/users';
const REVERB_USERS_CHANNEL = 'private-users.page';
const PUBLIC_EVENT_NAME = 'access.user.deleted';
const PUBLIC_EVENT_SCHEMA = 1;
const REVERB_EVENT_NAME = 'access.users.invalidated';

interface PrivatePublisher
{
    public function pushPrivate(string $topic, string $message): void;
}

final readonly class UsersPageTopic
{
    public function __construct(
        public string $family,
        public string $address,
    ) {}
}

final readonly class UserDeleted
{
    public function __construct(
        public string $userId,
        public string $internalReason,
    ) {}
}

final readonly class CommittedEvent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $messageId,
        public string $occurredAt,
        public object $payload,
        public array $metadata,
    ) {}
}

final readonly class PublicRealtimeEnvelope
{
    /**
     * @param array<string, scalar> $payload
     * @param array<string, scalar> $metadata
     */
    public function __construct(
        public string $messageId,
        public string $eventName,
        public int $schemaVersion,
        public string $occurredAt,
        public string $topic,
        public array $payload,
        public array $metadata,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'event_name' => $this->eventName,
            'schema_version' => $this->schemaVersion,
            'occurred_at' => $this->occurredAt,
            'topic' => $this->topic,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}

final readonly class UserDeletedTransformer
{
    public function transform(CommittedEvent $event, UsersPageTopic $topic): PublicRealtimeEnvelope
    {
        expect($event->payload instanceof UserDeleted, 'Transformer must reject the wrong domain event.');
        expect($topic->family === USERS_TOPIC_FAMILY, 'Transformer must reject an unapproved topic family.');

        return new PublicRealtimeEnvelope(
            $event->messageId,
            PUBLIC_EVENT_NAME,
            PUBLIC_EVENT_SCHEMA,
            $event->occurredAt,
            $topic->address,
            ['invalidate' => USERS_TOPIC_FAMILY],
            array_intersect_key($event->metadata, ['correlation_id' => true]),
        );
    }
}

final readonly class UserDeletedRealtimeSubscriber
{
    public function __construct(
        private UserDeletedTransformer $transformer,
        private PrivatePublisher $publisher,
        private UsersPageTopic $topic,
    ) {}

    public function receive(CommittedEvent $event): PublicRealtimeEnvelope
    {
        $envelope = $this->transformer->transform($event, $this->topic);
        $this->publisher->pushPrivate($envelope->topic, $envelope->toJson());

        return $envelope;
    }
}

final readonly class MercurePrivatePublisher implements PrivatePublisher
{
    public function __construct(private HubInterface $hub) {}

    public function pushPrivate(string $topic, string $message): void
    {
        try {
            $this->hub->publish(new Update($topic, $message, private: true));
        } catch (Throwable $throwable) {
            throw new RuntimeException($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }
}

final readonly class ReverbPrivatePublisher implements PrivatePublisher
{
    public function __construct(private PusherBroadcaster $broadcaster) {}

    public function pushPrivate(string $topic, string $message): void
    {
        $this->broadcaster->broadcast(
            [$topic],
            REVERB_EVENT_NAME,
            json_decode($message, true, flags: JSON_THROW_ON_ERROR),
        );
    }
}

final class CommittedMutation
{
    public bool $committed = false;
    public bool $deleted = false;

    public function commitDeletion(): CommittedEvent
    {
        $this->deleted = true;
        $this->committed = true;

        return prototypeEvent();
    }
}

final readonly class CommitAwarePublisher implements PrivatePublisher
{
    public function __construct(
        private CommittedMutation $mutation,
        private PrivatePublisher $next,
    ) {}

    public function pushPrivate(string $topic, string $message): void
    {
        expect($this->mutation->committed && $this->mutation->deleted, 'Realtime publication must run after commit.');
        $this->next->pushPrivate($topic, $message);
    }
}

function prototypeEvent(): CommittedEvent
{
    return new CommittedEvent(
        '018f4f5a-2266-7d1f-b965-a783bbd5c102',
        '2026-08-14T08:15:30.123456Z',
        new UserDeleted('user-secret-123', 'private administrative reason'),
        [
            'correlation_id' => 'corr-017',
            'causation_id' => 'internal-cause',
            'trace_id' => 'internal-trace',
        ],
    );
}

/** @return array<string, mixed> */
function verifyEnvelope(PublicRealtimeEnvelope $envelope, string $topic): array
{
    $body = $envelope->toArray();
    expect($body['message_id'] === '018f4f5a-2266-7d1f-b965-a783bbd5c102', 'Message identity must be preserved.');
    expect($body['event_name'] === PUBLIC_EVENT_NAME, 'Stable public event name mismatch.');
    expect($body['schema_version'] === PUBLIC_EVENT_SCHEMA, 'Public schema version mismatch.');
    expect($body['occurred_at'] === '2026-08-14T08:15:30.123456Z', 'Occurrence time mismatch.');
    expect($body['topic'] === $topic, 'Authorized topic mismatch.');
    expect($body['payload'] === ['invalidate' => USERS_TOPIC_FAMILY], 'Envelope must carry invalidation, not domain state.');
    expect($body['metadata'] === ['correlation_id' => 'corr-017'], 'Envelope metadata must be allowlisted.');
    expect(!str_contains($envelope->toJson(), 'user-secret-123'), 'User identity leaked into page invalidation.');
    expect(!str_contains($envelope->toJson(), 'private administrative reason'), 'Private domain field leaked.');
    expect(!str_contains($envelope->toJson(), UserDeleted::class), 'PHP FQCN leaked.');
    expect(!str_contains($envelope->toJson(), 'internal-trace'), 'Arbitrary metadata leaked.');

    return $body;
}

/** @param array<string, mixed> $receipt */
function writeReceipt(string $name, array $receipt): void
{
    file_put_contents(
        __DIR__ . '/receipts/' . $name . '.json',
        json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
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
