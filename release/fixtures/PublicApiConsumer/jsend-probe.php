<?php

declare(strict_types=1);

use Fight\Common\Adapter\Http\Symfony\JSendResponse as TypedJSendResponse;
use Fight\Common\Adapter\HttpFoundation\JSendResponse as LegacyJSendResponse;
use Fight\Common\Application\Http\JSend\JSendEnvelope;
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;
use Fight\Common\Domain\Type\Arrayable;

require $argv[2];

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

    $legacySuccess = LegacyJSendResponse::success(
        ['id' => 42],
        202,
        ['X-JSend' => 'legacy-success']
    );
    $legacyFail = LegacyJSendResponse::fail(
        ['email' => 'invalid'],
        422,
        ['X-JSend' => 'legacy-fail']
    );
    $legacyError = LegacyJSendResponse::error(
        'The bridge is out',
        502,
        ['request_id' => 'request-42'],
        4102,
        ['Retry-After' => '30']
    );
    $legacy = [
        'success'                      => [
            'body'    => $legacySuccess->getData(),
            'status'  => $legacySuccess->getStatusCode(),
            'headers' => ['x-jsend' => $legacySuccess->headers->all()['x-jsend']],
            'options' => $legacySuccess->getEncodingOptions(),
            'type'    => $legacySuccess->isSuccess() ? 'success' : 'invalid'
        ],
        'success_null'                 => LegacyJSendResponse::success()->getData(),
        'fail'                         => [
            'body'    => $legacyFail->getData(),
            'status'  => $legacyFail->getStatusCode(),
            'headers' => ['x-jsend' => $legacyFail->headers->all()['x-jsend']],
            'options' => $legacyFail->getEncodingOptions(),
            'type'    => $legacyFail->isFail() ? 'fail' : 'invalid'
        ],
        'error'                        => [
            'body'    => $legacyError->getData(),
            'status'  => $legacyError->getStatusCode(),
            'headers' => ['retry-after' => $legacyError->headers->all()['retry-after']],
            'options' => $legacyError->getEncodingOptions(),
            'type'    => $legacyError->isError() ? 'error' : 'invalid'
        ],
        'error_optional_fields_absent' => LegacyJSendResponse::error('Optional fields are absent')->getData(),
        'caller_selected_encoding'     => LegacyJSendResponse::success(
            ['url' => 'https://example.com/path'],
            options: 0
        )->getContent(),
        'runtime_deprecations'         => &$runtimeDeprecations
    ];

    $typed = ['available' => false];
    if (class_exists(JSendEnvelope::class)) {
        $presentation = static fn (array $data): Arrayable => new readonly class ($data) implements Arrayable {
            /** @param array<array-key, mixed> $data */
            public function __construct(private array $data)
            {
            }

            /** @return array<array-key, mixed> */
            public function toArray(): array
            {
                return $this->data;
            }
        };

        $records = ArrayList::of(Arrayable::class);
        $records->add($presentation(['id' => 42, 'name' => 'Frodo']));
        $records->add($presentation(['id' => 43, 'name' => 'Samwise']));
        $paginated = new ResultSet(2, 2, 5, $records);
        $typedResponse = TypedJSendResponse::error(
            'The bridge is out',
            502,
            $presentation(['request_id' => 'request-42']),
            4102,
            ['Retry-After' => '30']
        );

        try {
            TypedJSendResponse::success($presentation(['value' => "\xB1\x31"]));
            $invalidEncoding = 'not_rejected';
        } catch (JsonException) {
            $invalidEncoding = 'JsonException';
        }

        $typed = [
            'available'        => true,
            'single'           => JSendEnvelope::success($presentation(['id' => 42]))->toArray(),
            'fail'             => JSendEnvelope::fail($presentation(['email' => 'invalid']))->toArray(),
            'paginated'        => JSendEnvelope::success($paginated)->toArray(),
            'response'         => [
                'body'         => $typedResponse->getContent(),
                'status'       => $typedResponse->getStatusCode(),
                'headers'      => ['retry-after' => $typedResponse->headers->all()['retry-after']],
                'content_type' => $typedResponse->headers->get('Content-Type')
            ],
            'encoding_option_79' => JSendEnvelope::success($presentation([
                'url'        => 'https://example.com/path',
                'tag'        => '<safe>',
                'quote'      => '"',
                'apostrophe' => "'",
                'ampersand'  => '&'
            ]))->toJson(),
            'invalid_encoding' => $invalidEncoding
        ];
    }
} finally {
    restore_error_handler();
}

$typedAvailable = $typed['available'];
$findings = [[
    'finding_id'  => 'release.compatibility.consumer.jsend-legacy-passed',
    'evidence_id' => 'fight-common.behavior.jsend-legacy-response',
    'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
    'status'      => 'passed'
]];
if ($typedAvailable) {
    $findings[] = [
        'finding_id'  => 'release.compatibility.consumer.jsend-typed-passed',
        'evidence_id' => 'fight-common.behavior.jsend-typed-response',
        'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
        'status'      => 'passed'
    ];
}

echo json_encode(
    [
        'schema_version' => 'fight-common.jsend-probe/v1',
        'findings'       => $findings,
        'observations'   => ['jsend' => ['legacy' => $legacy, 'typed' => $typed]]
    ],
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
)."\n";
