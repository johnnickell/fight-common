<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task\Projection;

use Fight\Common\Domain\Mcp\Task\Task;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class TaskProjection
 *
 * @internal Shared Query projection, not a consumer authorization or setup step
 */
final class TaskProjection
{
    /**
     * Creates a complete bounded Query view without conferring disclosure authority
     */
    public static function fromTask(Task $task, string $context, int $now): StrictJson
    {
        return StrictJson::fromObject([
            'id'            => $task->definition()->id()->toString(),
            'version'       => $task->version(),
            'state'         => $task->stateAt($now)->data(),
            'created_at'    => $task->createdAt(),
            'updated_at'    => $task->updatedAt(),
            'ttl'           => $task->ttl(),
            'poll_interval' => $task->pollInterval(),
            'revision'      => self::revision($task, $context, $now)
        ], 40);
    }

    /**
     * Creates visibility-bound comparison encoding from the same immutable aggregate
     */
    private static function revision(Task $task, string $context, int $now): StrictJson
    {
        Task::timestamp($now);
        TaskDefinition::reference($context);
        $key = $task->incarnation();
        $offset = "\0".substr(hash_hmac('sha256', "offset\0".$context, $key, true), 0, 16);
        $carry = $task->publicOrder($now);
        // A context-specific additive mask preserves order without exposing a counter across visibility epochs.
        // The leading zero reserves carry space, so the fixed-width 136-bit value cannot wrap.
        for ($index = 16; $index >= 0; --$index) {
            $sum = ord($offset[$index]) + ($carry & 255);
            $offset[$index] = chr($sum & 255);
            $carry = ($carry >> 8) + ($sum >> 8);
        }

        return StrictJson::fromObject([
            'scope' => hash_hmac('sha256', "scope\0".$context, $key),
            'value' => 'v1:'.bin2hex($offset)
        ]);
    }
}
