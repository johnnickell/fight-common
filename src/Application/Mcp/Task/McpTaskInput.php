<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Task\Exception\TaskConflict;
use Fight\Common\Application\Task\Exception\TaskRejected;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Task\Command\SupplyTaskInput;
use Fight\Common\Domain\Task\TaskDefinition;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Throwable;

/**
 * Class McpTaskInput
 *
 * Method implementation only; complete Tasks activation owns registration and advertisement.
 */
final readonly class McpTaskInput
{
    /**
     * Constructs McpTaskInput
     */
    public function __construct(private CommandBus $commands, private string $scope, private string $caller)
    {
        TaskDefinition::reference($scope);
        TaskDefinition::reference($caller);
    }

    /**
     * Handles negotiated bounded tasks/update through the shared durable input Command
     */
    public function handle(McpRequest $request): McpResult
    {
        if ($request->method() !== 'tasks/update') {
            throw new McpProtocolException(McpProtocolError::methodNotFound(), $request->id());
        }

        $capabilities = $request->metadata()->clientCapabilities();
        $extensions = $capabilities->get('extensions');
        if (
            !$extensions instanceof StrictJson
            || !$extensions->get('io.modelcontextprotocol/tasks') instanceof StrictJson
        ) {
            throw new McpProtocolException(McpProtocolError::missingTasks(), $request->id());
        }

        $elicitation = $capabilities->get('elicitation');
        if (!$elicitation instanceof StrictJson || !$elicitation->get('form') instanceof StrictJson) {
            throw new McpProtocolException(McpProtocolError::missingFormElicitation(), $request->id());
        }

        try {
            $parameters = $request->parameters();
            if (
                count($parameters) !== 2 || !is_string($parameters['taskId'] ?? null)
                || !array_key_exists('inputResponses', $parameters)
            ) {
                throw new DomainException('Invalid Task update parameters.');
            }

            $command = new SupplyTaskInput(
                $this->scope,
                $parameters['taskId'],
                $this->caller,
                StrictJson::fromData($parameters['inputResponses'], 32)
            );
        } catch (Throwable) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        try {
            $this->commands->execute($command);
        } catch (TaskRejected | TaskConflict) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        // Storage/uncertain-commit/postcommit failures propagate to the existing safe central diagnostic path.
        return McpResult::boundedComplete([], 128);
    }
}
