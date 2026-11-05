<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Tool\Execution;

use Fight\Common\Application\Mcp\Detail\Data\CapturedDetail;
use Fight\Common\Application\Mcp\Detail\Data\MessagePage;
use Fight\Common\Application\Mcp\Observation\Data\ActivityPage;
use Fight\Common\Application\Mcp\Phase\Data\PhasePage;
use Fight\Common\Application\Mcp\Session\Data\ExecutionInspection;
use Fight\Common\Application\Mcp\Session\Data\ExecutionPage;
use Fight\Common\Application\Mcp\Session\Data\ExecutionSummary;
use Fight\Common\Application\Mcp\Tool\McpToolOutput;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class ExecutionToolOutput
 *
 * @internal
 */
final class ExecutionToolOutput
{
    /**
     * Creates a bounded projection exclusively from typed admitted Query results
     *
     * Missing or concealed selected evidence remains null, not a fabricated empty history.
     */
    public static function create(
        ExecutionSummary|ExecutionPage|ExecutionInspection|PhasePage|ActivityPage|MessagePage
        |CapturedDetail|null $result
    ): McpToolOutput {
        $data = $result?->toArray();
        if (strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 262144) {
            throw new DomainException('Execution Tool projection budget exceeded.');
        }

        return McpToolOutput::boundedStructured($data);
    }
}
