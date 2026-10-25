<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice;

/**
 * Class NoticeToolSchemas
 *
 * @internal
 */
final class NoticeToolSchemas
{
    public const array REFERENCE = ['type' => 'string', 'minLength' => 1, 'maxLength' => 96];
    public const array NULL_REFERENCE = ['type' => ['string', 'null'], 'maxLength' => 96];
    public const array TIME = ['type' => ['string', 'null'], 'maxLength' => 27];
    public const array TOKEN = ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048];
    public const array FILTERS = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'properties'           => [
            'id'             => self::REFERENCE,
            'producer'       => self::REFERENCE,
            'topic'          => self::REFERENCE,
            'type'           => self::REFERENCE,
            'subject'        => self::REFERENCE,
            'recipient'      => self::REFERENCE,
            'author'         => self::REFERENCE,
            'command'        => self::REFERENCE,
            'event'          => self::REFERENCE,
            'action'         => self::REFERENCE,
            'recorded_from'  => ['type' => 'string', 'maxLength' => 27],
            'recorded_until' => ['type' => 'string', 'maxLength' => 27],
            'occurred_from'  => ['type' => 'string', 'maxLength' => 27],
            'occurred_until' => ['type' => 'string', 'maxLength' => 27]
        ]
    ];
    public const array INPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['scopes'],
        'properties'           => [
            'scopes'       => ['type' => 'array', 'items' => self::REFERENCE, 'maxItems' => 16],
            'filters'      => self::FILTERS,
            'continuation' => self::TOKEN,
            'batch_size'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 32],
            'scan_limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]
        ]
    ];
    public const array RELATIONSHIPS = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['subject', 'recipients', 'author', 'command', 'event', 'action'],
        'properties'           => [
            'subject'    => self::NULL_REFERENCE,
            'recipients' => [
                'type'     => ['array', 'null'],
                'items'    => self::REFERENCE,
                'maxItems' => 32
            ],
            'author'     => self::NULL_REFERENCE,
            'command'    => self::NULL_REFERENCE,
            'event'      => self::NULL_REFERENCE,
            'action'     => self::NULL_REFERENCE
        ]
    ];
    public const array NOTICE = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => [
            'version', 'id', 'scope', 'producer', 'topic', 'type', 'occurred_at',
            'metadata', 'relationships', 'recorded_at'
        ],
        'properties'           => [
            'version'       => ['enum' => [1]],
            'id'            => self::REFERENCE,
            'scope'         => self::REFERENCE,
            'producer'      => self::REFERENCE,
            'topic'         => self::REFERENCE,
            'type'          => self::REFERENCE,
            'occurred_at'   => self::TIME,
            'metadata'      => ['type' => 'object'],
            'relationships' => self::RELATIONSHIPS,
            'recorded_at'   => ['type' => 'string', 'maxLength' => 27]
        ]
    ];
    public const array OUTPUT = [
        'type'                 => 'object',
        'additionalProperties' => false,
        'required'             => ['notices', 'status', 'continuation'],
        'properties'           => [
            'notices'      => ['type' => 'array', 'maxItems' => 32, 'items' => self::NOTICE],
            'status'       => ['enum' => ['complete', 'incomplete', 'uncertain']],
            'continuation' => self::TOKEN
        ]
    ];
}
