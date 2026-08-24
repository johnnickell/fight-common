<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class JSendEvidenceAuthority
 */
final readonly class JSendEvidenceAuthority
{
    /**
     * Returns the stable JSend finding set for one installed package
     *
     * @return list<array{finding_id: string, evidence_id: string, attribution: string, status: string}>
     */
    public static function findings(bool $typed): array
    {
        $findings = [[
            'finding_id'  => 'release.compatibility.consumer.jsend-legacy-passed',
            'evidence_id' => 'fight-common.behavior.jsend-legacy-response',
            'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
            'status'      => 'passed'
        ]];

        if ($typed) {
            $findings[] = [
                'finding_id'  => 'release.compatibility.consumer.jsend-typed-passed',
                'evidence_id' => 'fight-common.behavior.jsend-typed-response',
                'attribution' => 'release/fixtures/PublicApiConsumer/jsend-probe.php',
                'status'      => 'passed'
            ];
        }

        return $findings;
    }

    /**
     * Authenticates raw JSend-probe evidence before aggregate receipt composition
     */
    public static function isProbeReceipt(mixed $receipt): bool
    {
        $jsend = is_array($receipt) ? ($receipt['observations']['jsend'] ?? null) : null;
        $typed = is_array($jsend) ? ($jsend['typed'] ?? null) : null;
        $typedAvailable = is_array($typed) ? ($typed['available'] ?? null) : null;
        $runtimeDeprecations = is_array($jsend) ? ($jsend['legacy']['runtime_deprecations'] ?? null) : null;
        $expected = is_bool($typedAvailable) ? self::observation($typedAvailable) : null;
        if (is_array($expected)) {
            $expected['legacy']['runtime_deprecations'] = $runtimeDeprecations;
        }

        return is_bool($typedAvailable)
            && ($receipt['schema_version'] ?? null) === 'fight-common.jsend-probe/v1'
            && ($receipt['findings'] ?? null) === self::findings($typedAvailable)
            && is_array($receipt['observations'] ?? null)
            && array_keys($receipt['observations']) === ['jsend']
            && self::runtimeDeprecationsAreNormalized($runtimeDeprecations)
            && $jsend === $expected;
    }

    /**
     * Returns the exact baseline or candidate JSend observation
     *
     * @return array<string, mixed>
     */
    public static function observation(bool $typed): array
    {
        return [
            'legacy' => self::legacyObservation(),
            'typed'  => $typed ? self::typedObservation() : ['available' => false]
        ];
    }

    /**
     * Returns the exact legacy JSend observation
     *
     * @return array<string, mixed>
     */
    private static function legacyObservation(): array
    {
        return [
            'success'                      => [
                'body'    => ['status' => 'success', 'data' => ['id' => 42]],
                'status'  => 202,
                'headers' => ['x-jsend' => ['legacy-success']],
                'options' => 79,
                'type'    => 'success'
            ],
            'success_null'                 => ['status' => 'success', 'data' => null],
            'fail'                         => [
                'body'    => ['status' => 'fail', 'data' => ['email' => 'invalid']],
                'status'  => 422,
                'headers' => ['x-jsend' => ['legacy-fail']],
                'options' => 79,
                'type'    => 'fail'
            ],
            'error'                        => [
                'body'    => [
                    'status'  => 'error',
                    'message' => 'The bridge is out',
                    'data'    => ['request_id' => 'request-42'],
                    'code'    => 4102
                ],
                'status'  => 502,
                'headers' => ['retry-after' => ['30']],
                'options' => 79,
                'type'    => 'error'
            ],
            'error_optional_fields_absent' => [
                'status'  => 'error',
                'message' => 'Optional fields are absent'
            ],
            'caller_selected_encoding'     => '{"status":"success","data":{"url":"https:\\/\\/example.com\\/path"}}',
            'runtime_deprecations'         => []
        ];
    }

    /**
     * Returns the exact typed JSend observation
     *
     * @return array<string, mixed>
     */
    private static function typedObservation(): array
    {
        return [
            'available'          => true,
            'single'             => ['status' => 'success', 'data' => ['id' => 42]],
            'fail'               => ['status' => 'fail', 'data' => ['email' => 'invalid']],
            'paginated'          => [
                'status' => 'success',
                'data'   => [
                    'page'          => 2,
                    'per_page'      => 2,
                    'total_pages'   => 3,
                    'total_records' => 5,
                    'records'       => [
                        ['id' => 42, 'name' => 'Frodo'],
                        ['id' => 43, 'name' => 'Samwise']
                    ]
                ]
            ],
            'response'           => [
                'body'         => implode('', [
                    '{"status":"error","message":"The bridge is out",',
                    '"data":{"request_id":"request-42"},"code":4102}'
                ]),
                'status'       => 502,
                'headers'      => ['retry-after' => ['30']],
                'content_type' => 'application/json'
            ],
            'encoding_option_79' => implode('', [
                '{"status":"success","data":{"url":"https://example.com/path",',
                '"tag":"\u003Csafe\u003E","quote":"\u0022","apostrophe":"\u0027",',
                '"ampersand":"\u0026"}}'
            ]),
            'invalid_encoding'   => 'JsonException'
        ];
    }

    /**
     * Validates deterministic runtime-deprecation evidence without file or stack instability
     */
    private static function runtimeDeprecationsAreNormalized(mixed $deprecations): bool
    {
        return is_array($deprecations)
            && array_is_list($deprecations)
            && array_all(
                $deprecations,
                static fn (mixed $deprecation): bool => is_array($deprecation)
                    && array_keys($deprecation) === ['severity', 'message']
                    && in_array($deprecation['severity'], ['E_DEPRECATED', 'E_USER_DEPRECATED'], true)
                    && is_string($deprecation['message'])
            );
    }
}
