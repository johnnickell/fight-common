<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution;

use Fight\Common\Application\Mcp\Tool\McpToolSchemaValidator;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class ExecutionToolInput
 *
 * @internal
 */
final class ExecutionToolInput
{
    /**
     * Validates the exact selection fields even for direct Tool calls
     *
     * @phpstan-param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function read(ApplicationData $data, array $schema): array
    {
        $json = StrictJson::fromObject($data->toArray());
        if (!McpToolSchemaValidator::matches($json, StrictJson::fromObject($schema))) {
            throw new DomainException('Invalid execution Tool selection.');
        }

        // Only the declared JSON selection is translated; no envelope or authority fields are copied.
        return json_decode($json->toString(), true, flags: JSON_THROW_ON_ERROR);
    }
}
