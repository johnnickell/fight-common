<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Task;

use DateTimeImmutable;
use Fight\Common\Application\Mcp\McpCapability;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Task\Query\GetTask;
use Fight\Common\Domain\Mcp\Task\TaskDefinition;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpTasks
 */
final readonly class McpTasks implements McpCapability
{
    public const string EXTENSION = 'io.modelcontextprotocol/tasks';
    public const int MAX_RESPONSE_BYTES = 65536;

    private McpTaskInput $input;
    private McpTaskCancellation $cancellation;

    /**
     * Constructs McpTasks
     *
     * Buses must route the actual shared use cases; scope/caller come from trusted request composition.
     */
    public function __construct(
        private QueryBus $queries,
        CommandBus $commands,
        private string $scope,
        string $caller,
        private int $maxResponseBytes = self::MAX_RESPONSE_BYTES
    ) {
        TaskDefinition::reference($scope);
        if ($maxResponseBytes < 1 || $maxResponseBytes > self::MAX_RESPONSE_BYTES) {
            throw new DomainException('Invalid Task response budget.');
        }

        $this->input = new McpTaskInput($commands, $scope, $caller);
        $this->cancellation = new McpTaskCancellation($commands, $scope, $caller);
    }

    /**
     * Requires current per-request negotiation before asynchronous effects or protected reads
     */
    public static function requireSupport(?McpRequest $request): void
    {
        $extensions = $request?->metadata()->clientCapabilities()->get('extensions');
        if (!$extensions instanceof StrictJson || !$extensions->get(self::EXTENSION) instanceof StrictJson) {
            throw new McpProtocolException(McpProtocolError::missingTasks(), $request?->id());
        }
    }

    /**
     * @inheritDoc
     */
    public function methods(): array
    {
        return ['tasks/get', 'tasks/update', 'tasks/cancel'];
    }

    /**
     * @inheritDoc
     */
    public function capabilities(): array
    {
        return ['extensions' => [self::EXTENSION => StrictJson::fromObject()]];
    }

    /**
     * @inheritDoc
     */
    public function mirrorDeclarations(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function validate(McpRequest $request): void
    {
        if (!in_array($request->method(), $this->methods(), true)) {
            throw new McpProtocolException(McpProtocolError::methodNotFound(), $request->id());
        }

        self::requireSupport($request);
    }

    /**
     * @inheritDoc
     */
    public function handle(McpRequest $request): McpResult
    {
        $this->validate($request);
        if ($request->method() === 'tasks/update') {
            return $this->input->handle($request)->withResponseLimit($this->maxResponseBytes);
        }

        if ($request->method() === 'tasks/cancel') {
            return $this->cancellation->handle($request)->withResponseLimit($this->maxResponseBytes);
        }

        $parameters = $request->parameters();
        if (count($parameters) !== 1 || !is_string($parameters['taskId'] ?? null)) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        try {
            TaskDefinition::identity($parameters['taskId']);
        } catch (DomainException) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        return McpResult::complete($this->project($this->read($parameters['taskId']), true))
            ->withResponseLimit($this->maxResponseBytes);
    }

    /**
     * Returns only freshly authorized durable metadata after a Tool returns a handle
     *
     * @internal
     */
    public function created(McpTaskHandle $handle): McpResult
    {
        return McpResult::task($this->project($this->read($handle->id()), false))
            ->withResponseLimit($this->maxResponseBytes);
    }

    /**
     * Retrieves current state through the shared Query rather than Tool catalog or adapter SQL
     */
    private function read(string $id): StrictJson
    {
        $view = $this->queries->fetch(new GetTask($this->scope, $id));
        if ($view === null) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        if (!$view instanceof StrictJson || $view->get('id') !== $id) {
            throw new DomainException('Invalid Task Query result.');
        }

        return $view;
    }

    /**
     * Projects complete coherent admitted state without exposing original context or comparison data
     *
     * @return array<string, mixed>
     */
    private function project(StrictJson $view, bool $detailed): array
    {
        $state = $view->get('state');
        $data = [
            'taskId'        => $view->get('id'),
            'status'        => $state->get('status'),
            'createdAt'     => $this->timestamp($view->get('created_at')),
            'lastUpdatedAt' => $this->timestamp($view->get('updated_at')),
            'ttlMs'         => $view->get('ttl')
        ];
        if ($state->get('message') !== null) {
            $data['statusMessage'] = $state->get('message');
        }

        if ($view->get('poll_interval') !== null) {
            $data['pollIntervalMs'] = $view->get('poll_interval');
        }

        if ($detailed) {
            if ($state->get('status') === 'input_required') {
                $requests = [];
                foreach ($state->get('input')->properties() as $key => $form) {
                    $requests[$key] = StrictJson::fromObject([
                        'method' => 'elicitation/create',
                        'params' => StrictJson::fromObject([
                            'mode'            => 'form',
                            'message'         => $form->get('message'),
                            'requestedSchema' => $form->get('schema')
                        ])
                    ]);
                }

                $data['inputRequests'] = StrictJson::fromObject($requests);
            }

            foreach (['result', 'error'] as $field) {
                if ($state->get($field) !== null) {
                    $data[$field] = $state->get($field);
                }
            }
        }

        return $data;
    }

    /**
     * Returns exact UTC millisecond timestamps without ambient timezone conversion
     */
    private function timestamp(int $milliseconds): string
    {
        return new DateTimeImmutable('@'.intdiv($milliseconds, 1000))->format('Y-m-d\TH:i:s').sprintf(
            '.%03dZ',
            $milliseconds % 1000
        );
    }
}
