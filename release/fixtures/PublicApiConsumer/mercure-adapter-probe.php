<?php

declare(strict_types=1);

use Fight\Common\Adapter\Socket\MercureHubPublisher;
use Fight\Common\Adapter\Socket\PrivateMercureHubPublisher;
use Fight\Common\Application\Socket\Exception\SocketException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

set_error_handler(
    static fn (int $severity): bool => $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED
);

try {
    foreach (array_slice($argv, 1) as $file) {
        require $file;
    }
} finally {
    restore_error_handler();
}

$publicHub = new class implements HubInterface {
    public ?Update $update = null;

    /**
     * Records a public update.
     */
    public function publish(Update $update): string
    {
        $this->update = $update;

        return 'public-update';
    }
};
$privateHub = new class implements HubInterface {
    public ?Update $update = null;

    /**
     * Records a private update.
     */
    public function publish(Update $update): string
    {
        $this->update = $update;

        return 'private-update';
    }
};
$failingHub = new class implements HubInterface {
    /**
     * Simulates a private transport failure.
     */
    public function publish(Update $update): string
    {
        throw new RuntimeException('private transport failed', 29);
    }
};

(new MercureHubPublisher($publicHub))->push('/topics/public', '{"visibility":"public"}');
(new PrivateMercureHubPublisher($privateHub))->pushPrivate('/topics/private', '{"visibility":"private"}');

try {
    (new PrivateMercureHubPublisher($failingHub))->pushPrivate('/topics/private', '{"visibility":"private"}');
    throw new RuntimeException('Private publication transport failure was not wrapped.');
} catch (SocketException $exception) {
    $failure = [
        'class'            => $exception::class,
        'message'          => $exception->getMessage(),
        'code'             => $exception->getCode(),
        'previous_class'   => $exception->getPrevious()::class,
        'previous_message' => $exception->getPrevious()->getMessage(),
        'previous_code'    => $exception->getPrevious()->getCode()
    ];
}

echo json_encode([
    'schema_version' => 'fight-common.mercure-adapter-probe/v1',
    'findings'       => [[
        'finding_id'  => 'release.compatibility.consumer.mercure-public-private-publishers-passed',
        'evidence_id' => 'fight-common.behavior.mercure-public-private-publishers',
        'attribution' => 'release/fixtures/PublicApiConsumer/mercure-adapter-probe.php',
        'status'      => 'passed'
    ]],
    'observations'   => [
        'public'          => [
            'topics' => $publicHub->update->getTopics(),
            'data'   => $publicHub->update->getData(),
            'private' => $publicHub->update->isPrivate(),
            'id'      => $publicHub->update->getId(),
            'type'    => $publicHub->update->getType(),
            'retry'   => $publicHub->update->getRetry()
        ],
        'private'         => [
            'topics' => $privateHub->update->getTopics(),
            'data'   => $privateHub->update->getData(),
            'private' => $privateHub->update->isPrivate(),
            'id'      => $privateHub->update->getId(),
            'type'    => $privateHub->update->getType(),
            'retry'   => $privateHub->update->getRetry()
        ],
        'private_failure' => $failure
    ]
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
