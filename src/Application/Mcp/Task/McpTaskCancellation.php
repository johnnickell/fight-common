<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\Task\Exception\TaskConflict;
use Fight\Common\Application\Mcp\Task\Exception\TaskRejected;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Command\RequestTaskCancellation;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Throwable;

/**
 * Class McpTaskCancellation
 *
 * Method implementation only; complete Tasks activation owns registration and advertisement.
 */
final readonly class McpTaskCancellation
{
    /**
     * Constructs McpTaskCancellation
     */
    public function __construct(private CommandBus $commands, private string $scope, private string $caller)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::reference($caller);
    }

    /**
     * Handles negotiated bounded tasks/cancel through the shared cancellation Command
     */
    public function handle(McpRequest $request): McpResult
    {
        if ($request->method() !== 'tasks/cancel') {
            throw new McpProtocolException(McpProtocolError::methodNotFound(), $request->id());
        }

        $extensions = $request->metadata()->clientCapabilities()->get('extensions');
        if (
            !$extensions instanceof StrictJson
            || !$extensions->get('io.modelcontextprotocol/tasks') instanceof StrictJson
        ) {
            throw new McpProtocolException(McpProtocolError::missingTasks(), $request->id());
        }

        try {
            $parameters = $request->parameters();
            if (count($parameters) !== 1 || !is_string($parameters['taskId'] ?? null)) {
                throw new DomainException('Invalid Task cancellation parameters.');
            }

            $command = new RequestTaskCancellation($this->scope, $parameters['taskId'], $this->caller);
        } catch (Throwable) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        try {
            $this->commands->execute($command);
        } catch (TaskRejected | TaskConflict) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        // Other failures retain the existing central safe -32603 diagnostic path, not a false acknowledgment.
        return McpResult::boundedComplete([], 128);
    }
}
