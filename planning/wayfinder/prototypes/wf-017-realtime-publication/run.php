<?php

declare(strict_types=1);

use Fight\Common\Adapter\Socket\MercureHubPublisher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Prototype\RealtimePublication\CommitAwarePublisher;
use Prototype\RealtimePublication\CommittedMutation;
use Prototype\RealtimePublication\MercurePrivatePublisher;
use Prototype\RealtimePublication\PrivatePublisher;
use Prototype\RealtimePublication\ReverbPrivatePublisher;
use Prototype\RealtimePublication\UserDeletedRealtimeSubscriber;
use Prototype\RealtimePublication\UserDeletedTransformer;
use Prototype\RealtimePublication\UsersPageTopic;
use Pusher\Pusher;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

use const Prototype\RealtimePublication\MERCURE_USERS_TOPIC;
use const Prototype\RealtimePublication\REVERB_EVENT_NAME;
use const Prototype\RealtimePublication\REVERB_USERS_CHANNEL;
use const Prototype\RealtimePublication\USERS_TOPIC_FAMILY;

use function Prototype\RealtimePublication\expect;
use function Prototype\RealtimePublication\lockedVersions;
use function Prototype\RealtimePublication\verifyEnvelope;
use function Prototype\RealtimePublication\writeReceipt;

require __DIR__ . '/vendor/autoload.php';
require dirname(__DIR__, 4) . '/vendor/autoload.php';

/**
 * @param list<Update> $updates
 */
function mockHub(array &$updates): MockHub
{
    return new MockHub(
        'https://starter.example.test/.well-known/mercure',
        new StaticTokenProvider('prototype-publisher-token'),
        static function (Update $update) use (&$updates): string {
            $updates[] = $update;

            return 'prototype-update-' . count($updates);
        },
    );
}

/** @param array<string, string> $packages */
function runMercureLane(string $framework, array $packages): void
{
    $updates = [];
    $hub = mockHub($updates);

    // The live Fight Common adapter is the baseline under test: it cannot mark an update private.
    (new MercureHubPublisher($hub))->push(MERCURE_USERS_TOPIC, '{"baseline":true}');
    expect(count($updates) === 1 && !$updates[0]->isPrivate(), "$framework baseline must expose the current public-update gap.");

    $mutation = new CommittedMutation();
    $event = $mutation->commitDeletion();
    $subscriber = new UserDeletedRealtimeSubscriber(
        new UserDeletedTransformer(),
        new CommitAwarePublisher($mutation, new MercurePrivatePublisher($hub)),
        new UsersPageTopic(USERS_TOPIC_FAMILY, MERCURE_USERS_TOPIC),
    );
    $envelope = $subscriber->receive($event);

    expect(count($updates) === 2, "$framework must publish one candidate private update.");
    $privateUpdate = $updates[1];
    expect($privateUpdate->isPrivate(), "$framework candidate must mark the Mercure update private.");
    expect($privateUpdate->getTopics() === [MERCURE_USERS_TOPIC], "$framework Mercure topic mismatch.");
    expect($privateUpdate->getData() === $envelope->toJson(), "$framework Mercure body mismatch.");
    $body = verifyEnvelope($envelope, MERCURE_USERS_TOPIC);

    $beforeWrongFamily = count($updates);
    try {
        (new UserDeletedTransformer())->transform($event, new UsersPageTopic('access.users.secret', MERCURE_USERS_TOPIC));
        throw new RuntimeException('Wrong topic family unexpectedly transformed.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage() === 'Transformer must reject an unapproved topic family.', 'Wrong topic family failure mismatch.');
    }
    expect(count($updates) === $beforeWrongFamily, "$framework must not publish an unapproved topic family.");

    writeReceipt($framework, [
        'prototype' => 'WF-017 private realtime publication',
        'framework' => $framework,
        'packages' => $packages,
        'transport' => 'Mercure private Update',
        'topic' => MERCURE_USERS_TOPIC,
        'existing_publisher_private' => false,
        'candidate_private_publisher' => true,
        'post_commit_probe' => $mutation->committed && $mutation->deleted,
        'envelope' => $body,
        'leak_checks' => [
            'php_fqcn' => false,
            'domain_user_id' => false,
            'internal_reason' => false,
            'arbitrary_metadata' => false,
        ],
        'unapproved_topic_family_published' => false,
        'result' => 'passed',
    ]);
}

/** @param array<string, string> $packages */
function runLaravelLane(array $packages): void
{
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{}')]));
    $stack->push(Middleware::history($history));
    $client = new Client(['handler' => $stack]);
    $pusher = new Pusher(
        'prototype-key',
        'prototype-secret',
        'prototype-app',
        ['useTLS' => false, 'host' => 'reverb', 'port' => 8080, 'scheme' => 'http'],
        $client,
    );
    $broadcaster = new PusherBroadcaster($pusher);

    $mutation = new CommittedMutation();
    $event = $mutation->commitDeletion();
    $subscriber = new UserDeletedRealtimeSubscriber(
        new UserDeletedTransformer(),
        new CommitAwarePublisher($mutation, new ReverbPrivatePublisher($broadcaster)),
        new UsersPageTopic(USERS_TOPIC_FAMILY, REVERB_USERS_CHANNEL),
    );
    $envelope = $subscriber->receive($event);
    $body = verifyEnvelope($envelope, REVERB_USERS_CHANNEL);

    expect(count($history) === 1, 'Laravel must make one native Pusher/Reverb publish request.');
    $request = $history[0]['request'];
    $request->getBody()->rewind();
    $native = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    expect($native['channel'] === REVERB_USERS_CHANNEL, 'Laravel Reverb private channel mismatch.');
    expect($native['name'] === REVERB_EVENT_NAME, 'Laravel Reverb public event name mismatch.');
    expect(json_decode($native['data'], true, flags: JSON_THROW_ON_ERROR) === $body, 'Laravel Reverb envelope mismatch.');
    expect(str_contains((string) $request->getUri(), '/apps/prototype-app/events?'), 'Laravel must use the native Pusher/Reverb event endpoint.');

    writeReceipt('laravel', [
        'prototype' => 'WF-017 private realtime publication',
        'framework' => 'laravel',
        'packages' => $packages,
        'transport' => 'Laravel native Pusher broadcaster for Reverb',
        'channel' => REVERB_USERS_CHANNEL,
        'event' => REVERB_EVENT_NAME,
        'native_request_composed' => true,
        'post_commit_probe' => $mutation->committed && $mutation->deleted,
        'envelope' => $body,
        'leak_checks' => [
            'php_fqcn' => false,
            'domain_user_id' => false,
            'internal_reason' => false,
            'arbitrary_metadata' => false,
        ],
        'result' => 'passed',
    ]);
}

function verifyPublishFailureDoesNotUndoCommit(): void
{
    $mutation = new CommittedMutation();
    $event = $mutation->commitDeletion();
    $failingPublisher = new class implements PrivatePublisher {
        public function pushPrivate(string $topic, string $message): void
        {
            throw new RuntimeException('realtime transport unavailable');
        }
    };
    $subscriber = new UserDeletedRealtimeSubscriber(
        new UserDeletedTransformer(),
        new CommitAwarePublisher($mutation, $failingPublisher),
        new UsersPageTopic(USERS_TOPIC_FAMILY, MERCURE_USERS_TOPIC),
    );

    try {
        $subscriber->receive($event);
        throw new RuntimeException('Publication failure unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage() === 'realtime transport unavailable', 'Publication failure mismatch.');
    }

    expect($mutation->committed && $mutation->deleted, 'Post-commit publication failure must not undo committed state.');
}

$versions = lockedVersions();
runMercureLane('symfony', ['symfony/mercure' => $versions['symfony/mercure']]);
runLaravelLane([
    'illuminate/broadcasting' => $versions['illuminate/broadcasting'],
    'pusher/pusher-php-server' => $versions['pusher/pusher-php-server'],
]);
runMercureLane('yii', ['symfony/mercure' => $versions['symfony/mercure']]);
runMercureLane('codeigniter', [
    'codeigniter4/framework' => $versions['codeigniter4/framework'],
    'symfony/mercure' => $versions['symfony/mercure'],
]);
runMercureLane('slim', ['symfony/mercure' => $versions['symfony/mercure']]);
verifyPublishFailureDoesNotUndoCommit();

fwrite(STDOUT, "WF-017 private realtime publication prototype passed for Symfony, Laravel, Yii, CodeIgniter, and Slim.\n");
