<?php

declare(strict_types=1);

use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Value\Identifier\Uuid;

use function Fight\Common\Domain\array_list;

require $argv[1];

$list = array_list(['alpha', 'beta'], 'string');
$meta = Meta::create(['consumer' => 'disposable']);
$uuid = Uuid::fromString(Uuid::NIL);

echo json_encode(
    [
        'schema_version' => 'fight-common.public-api-probe/v1',
        'finding'        => [
            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
            'evidence_id' => 'fight-common.consumer.public-api-representative',
            'attribution' => 'release/fixtures/PublicApiConsumer/probe.php',
            'status'      => 'passed'
        ],
        'observations'   => [
            'uuid'       => $uuid->toString(),
            'meta'       => $meta->toArray(),
            'collection' => $list->toArray()
        ]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
