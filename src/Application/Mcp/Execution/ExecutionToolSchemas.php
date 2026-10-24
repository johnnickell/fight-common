<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

/**
 * Class ExecutionToolSchemas
 *
 * @internal
 */
final class ExecutionToolSchemas
{
    public const array REFERENCE = ['type' => 'string', 'minLength' => 1, 'maxLength' => 128];
    public const array NULL_REFERENCE = ['type' => ['string', 'null'], 'maxLength' => 128];
    public const array TIME = ['type' => ['string', 'null'], 'maxLength' => 27];
    public const array COUNT = ['type' => 'integer', 'minimum' => 0];
    public const array OUTCOME = ['enum' => ['unknown', 'succeeded', 'failed', 'cancelled']];
    public const array COMPLETENESS = ['enum' => ['complete', 'partial', 'unknown', 'unavailable']];
    public const array PAGE = [
        'page'     => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000001],
        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]
    ];
    public const array SELECTION = [
        'projects' => ['type' => 'array', 'items' => self::REFERENCE, 'maxItems' => 16],
        'filters'  => [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => ['session_id' => self::REFERENCE, 'consumer_reference' => self::REFERENCE]
        ]
    ];
    public const array EXACT = ['project_id' => self::REFERENCE, 'session_id' => self::REFERENCE];
    public const array SUMMARY_INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['projects'],
        'properties'           => self::SELECTION
    ];
    public const array LIST_INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['projects'],
        'properties'           => self::SELECTION + self::PAGE + ['direction' => ['enum' => ['ASC', 'DESC']]]
    ];
    public const array GET_INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['project_id', 'session_id'],
        'properties'           => self::EXACT
    ];
    public const array PHASE_INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['project_id', 'session_id'],
        'properties'           => self::EXACT + self::PAGE + [
            'direction' => ['enum' => ['ASC', 'DESC']],
            'filters'   => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => [
                    'phase_id' => self::REFERENCE,
                    'agent_id' => self::REFERENCE,
                    'name'     => self::REFERENCE
                ]
            ]
        ]
    ];
    public const array ACTIVITY_INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['project_id', 'session_id', 'phase_id'],
        'properties'           => self::EXACT + self::PAGE + [
            'phase_id'  => self::REFERENCE,
            'direction' => ['enum' => ['ASC', 'DESC']],
            'filters'   => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => [
                    'producer_id' => self::REFERENCE,
                    'kind'        => ['type' => 'string', 'maxLength' => 96],
                    'model_id'    => self::REFERENCE
                ]
            ]
        ]
    ];
    public const array COUNTS = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['complete', 'partial', 'unknown', 'unavailable'],
        'properties'           => [
            'complete'    => self::COUNT,
            'partial'     => self::COUNT,
            'unknown'     => self::COUNT,
            'unavailable' => self::COUNT
        ]
    ];
    public const array USAGE_TOTAL = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['metric', 'unit', 'provenance', 'measured', 'counts'],
        'properties'           => [
            'metric'     => self::REFERENCE,
            'unit'       => self::REFERENCE,
            'provenance' => self::REFERENCE,
            'measured'   => ['type' => ['string', 'null'], 'maxLength' => 1026],
            'counts'     => self::COUNTS
        ]
    ];
    public const array SUMMARY = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => [
            'executions',
            'outcomes',
            'activities',
            'activities_without_usage',
            'usage',
            'latest_recorded_at',
            'capture_completeness'
        ],
        'properties'           => [
            'executions'               => self::COUNT,
            'outcomes'                 => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['unknown', 'succeeded', 'failed', 'cancelled'],
                'properties'           => [
                    'unknown'   => self::COUNT,
                    'succeeded' => self::COUNT,
                    'failed'    => self::COUNT,
                    'cancelled' => self::COUNT
                ]
            ],
            'activities'               => self::COUNT,
            'activities_without_usage' => self::COUNT,
            'usage'                    => ['type' => 'array', 'items' => self::USAGE_TOTAL, 'maxItems' => 256],
            'latest_recorded_at'       => self::TIME,
            'capture_completeness'     => ['const' => 'unknown']
        ]
    ];
    public const array EXECUTION = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['session', 'outcome', 'latest_recorded_at'],
        'properties'           => [
            'session'            => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['project_id', 'session_id', 'consumer_reference'],
                'properties'           => self::EXACT + ['consumer_reference' => self::REFERENCE]
            ],
            'outcome'            => self::OUTCOME,
            'latest_recorded_at' => self::TIME
        ]
    ];
    public const array OBSERVATION = [
        'type'                 => ['object', 'null'],
        'additionalProperties' => false,
        'required'             => [
            'observation_id',
            'producer_id',
            'occurred_at',
            'recorded_at',
            'started_at',
            'ended_at',
            'outcome'
        ],
        'properties'           => [
            'observation_id' => self::REFERENCE,
            'producer_id'    => self::REFERENCE,
            'occurred_at'    => self::TIME,
            'recorded_at'    => self::TIME,
            'started_at'     => self::TIME,
            'ended_at'       => self::TIME,
            'outcome'        => self::OUTCOME
        ]
    ];
    public const array INSPECTION = [
        'type'                 => ['object', 'null'],
        'additionalProperties' => false,
        'required'             => ['execution', 'observation', 'summary'],
        'properties'           => [
            'execution'   => self::EXECUTION,
            'observation' => self::OBSERVATION,
            'summary'     => self::SUMMARY
        ]
    ];
    public const array PHASE = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['phase', 'observation', 'latest_recorded_at', 'models'],
        'properties'           => [
            'phase'              => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['session_id', 'phase_id', 'parent_phase_id', 'agent_id', 'name', 'attempt'],
                'properties'           => [
                    'session_id'      => self::REFERENCE,
                    'phase_id'        => self::REFERENCE,
                    'parent_phase_id' => self::NULL_REFERENCE,
                    'agent_id'        => self::REFERENCE,
                    'name'            => self::REFERENCE,
                    'attempt'         => ['type' => 'integer', 'minimum' => 1]
                ]
            ],
            'observation'        => self::OBSERVATION,
            'latest_recorded_at' => self::TIME,
            'models'             => ['type' => 'array', 'items' => self::REFERENCE, 'maxItems' => 128]
        ]
    ];
    public const array MEASUREMENT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['version', 'metric', 'unit', 'provenance', 'completeness', 'value'],
        'properties'           => [
            'version'      => ['const' => 1],
            'metric'       => self::REFERENCE,
            'unit'         => self::REFERENCE,
            'provenance'   => self::REFERENCE,
            'completeness' => self::COMPLETENESS,
            'value'        => ['type' => ['string', 'null'], 'maxLength' => 128]
        ]
    ];
    public const array SEGMENT = [
        'type'                 => ['object', 'null'],
        'additionalProperties' => false,
        'required'             => ['stream', 'index', 'offset'],
        'properties'           => [
            'stream' => ['enum' => ['stdout', 'stderr', 'combined']],
            'index'  => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647]
        ]
    ];
    public const array DETAIL = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => [
            'producer_id',
            'observation_id',
            'occurred_at',
            'recorded_at',
            'capture',
            'availability'
        ],
        'properties'           => [
            'producer_id'    => self::REFERENCE,
            'observation_id' => self::REFERENCE,
            'occurred_at'    => self::TIME,
            'recorded_at'    => self::TIME,
            'availability'   => ['enum' => ['retained', 'missing', 'absent', 'unavailable', 'expired']],
            'capture'        => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['version', 'activity_id', 'kind', 'content_type', 'completeness', 'segment'],
                'properties'           => [
                    'version'      => ['const' => 1],
                    'activity_id'  => self::REFERENCE,
                    'kind'         => ['enum' => ['message', 'tool_arguments', 'tool_result', 'process_output']],
                    'content_type' => self::REFERENCE,
                    'completeness' => ['enum' => ['complete', 'partial', 'absent', 'unavailable', 'expired']],
                    'segment'      => self::SEGMENT
                ]
            ]
        ]
    ];
    public const array ACTIVITY = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => [
            'producer_id',
            'observation_id',
            'occurred_at',
            'recorded_at',
            'agent_id',
            'activity',
            'details'
        ],
        'properties'           => [
            'producer_id'    => self::REFERENCE,
            'observation_id' => self::REFERENCE,
            'occurred_at'    => self::TIME,
            'recorded_at'    => self::TIME,
            'agent_id'       => self::NULL_REFERENCE,
            'activity'       => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['version', 'kind', 'summary', 'model_id', 'usage'],
                'properties'           => [
                    'version'  => ['const' => 1],
                    'kind'     => self::REFERENCE,
                    'summary'  => ['type' => 'string', 'maxLength' => 512],
                    'model_id' => self::NULL_REFERENCE,
                    'usage'    => ['type' => 'array', 'items' => self::MEASUREMENT, 'maxItems' => 32]
                ]
            ],
            'details'        => ['type' => 'array', 'items' => self::DETAIL, 'maxItems' => 32]
        ]
    ];
    public const array PAGE_OUTPUT = [
        'total'    => self::COUNT,
        'page'     => self::PAGE['page'],
        'per_page' => self::PAGE['per_page']
    ];
    public const array LIST_OUTPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['executions', 'total', 'page', 'per_page'],
        'properties'           => self::PAGE_OUTPUT + [
            'executions' => ['type' => 'array', 'items' => self::EXECUTION, 'maxItems' => 100]
        ]
    ];
    public const array PHASE_OUTPUT = [
        'type'                 => ['object', 'null'],
        'additionalProperties' => false,
        'required'             => ['phases', 'total', 'page', 'per_page'],
        'properties'           => self::PAGE_OUTPUT + [
            'phases' => ['type' => 'array', 'items' => self::PHASE, 'maxItems' => 100]
        ]
    ];
    public const array ACTIVITY_OUTPUT = [
        'type'                 => ['object', 'null'],
        'additionalProperties' => false,
        'required'             => ['activities', 'total', 'page', 'per_page'],
        'properties'           => self::PAGE_OUTPUT + [
            'activities' => ['type' => 'array', 'items' => self::ACTIVITY, 'maxItems' => 100]
        ]
    ];
}
