<?php

declare(strict_types=1);

use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Value\Identifier\Uuid;

use function Fight\Common\Domain\array_list;

$runtimeDeprecations = [];
set_error_handler(
    static function (int $severity, string $message) use (&$runtimeDeprecations): bool {
        if ($severity !== E_DEPRECATED && $severity !== E_USER_DEPRECATED) {
            return false;
        }

        $runtimeDeprecations[] = [
            'severity' => $severity === E_DEPRECATED ? 'E_DEPRECATED' : 'E_USER_DEPRECATED',
            'message'  => $message
        ];

        return true;
    }
);

try {
    require $argv[1];

    $list = array_list(['alpha', 'beta'], 'string');
    $meta = Meta::create(['consumer' => 'disposable']);
    $uuid = Uuid::fromString(Uuid::NIL);
} finally {
    restore_error_handler();
}

echo json_encode(
    [
        'schema_version' => 'fight-common.public-api-representative-probe/v1',
        'findings'       => [[
            'finding_id'  => 'release.compatibility.consumer.public-api-probe-passed',
            'evidence_id' => 'fight-common.consumer.public-api-representative',
            'attribution' => 'release/fixtures/PublicApiConsumer/public-api-probe.php',
            'status'      => 'passed'
        ]],
        'observations'   => [
            'uuid'                 => $uuid->toString(),
            'meta'                 => $meta->toArray(),
            'collection'           => $list->toArray(),
            'runtime_deprecations' => $runtimeDeprecations
        ]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
