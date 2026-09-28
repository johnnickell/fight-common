<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputRequired;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInputResponses;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInteractiveTool;
use Fight\Common\Application\Mcp\Tool\Interaction\McpToolInteraction;
use Fight\Common\Application\Mcp\Tool\Messaging\McpToolMetadataFilter;
use Fight\Common\Application\Validation\Data\ApplicationData;
use Fight\Common\Application\Validation\Exception\ValidationException;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;
use ReflectionMethod;
use Throwable;

/**
 * Class McpToolInvoker
 */
final readonly class McpToolInvoker
{
    /**
     * Constructs McpToolInvoker
     */
    public function __construct(
        private McpToolRegistry $registry,
        private McpToolAvailability $availability,
        private ValidationService $validation = new ValidationService(),
        private McpToolFailureMap $failures = new McpToolFailureMap(),
        private ?McpToolMetadataFilter $metadata = null,
        private ?McpToolInteraction $interaction = null
    ) {
    }

    /**
     * Invokes one available Tool with validated arguments and an optional request-scoped reporter
     *
     * Availability precedes argument inspection. Unexpected Tool exceptions cannot impersonate
     * protocol failures. The caller's central diagnostic boundary records them exactly once.
     */
    public function invoke(string $name, mixed $arguments, ?McpProgressReporter $progress = null): McpResult
    {
        return $this->invokeInitial($name, $arguments, $progress, null);
    }

    /**
     * Invokes or resumes a Tool using the current semantic request capability evidence
     *
     * Direct invoke calls carry no capability evidence and therefore cannot enter an interactive Tool.
     */
    public function invokeRequest(McpRequest $request, ?McpProgressReporter $progress = null): McpResult
    {
        $parameters = $request->parameters();
        if (
            $request->method() !== 'tools/call' || !is_string($parameters['name'] ?? null)
            || array_diff(array_keys($parameters), ['name', 'arguments', 'requestState', 'inputResponses']) !== []
        ) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        if (array_key_exists('requestState', $parameters) || array_key_exists('inputResponses', $parameters)) {
            return $this->resumeRequest($request, $progress);
        }

        $arguments = array_key_exists('arguments', $parameters) ? $parameters['arguments'] : StrictJson::fromObject();

        return $this->invokeInitial($parameters['name'], $arguments, $progress, $request);
    }

    /**
     * Returns mirrors only for a currently available Tool without reading arguments or invoking it
     *
     * Availability is reevaluated at invocation; no authorization snapshot is cached between boundaries.
     *
     * @return list<McpMirrorDeclaration>
     */
    public function mirrorsFor(string $name): array
    {
        $this->select($name);

        return $this->registry->mirrorsFor($name);
    }

    /**
     * Validates initial arguments only after availability and static capability gating
     */
    private function invokeInitial(
        string $name,
        mixed $arguments,
        ?McpProgressReporter $progress,
        ?McpRequest $request
    ): McpResult {
        [$tool, $info] = $this->select($name);
        $this->requireCapability($info, $request);

        try {
            $arguments = StrictJson::fromData($arguments);
        } catch (DomainException) {
            return $this->error('Tool arguments must contain supported JSON values.');
        }

        if (!$arguments->isObject()) {
            return $this->error('Tool arguments must be an object.');
        }

        if (!McpToolSchemaValidator::matches($arguments, $info->inputSchema())) {
            return $this->error('Tool arguments do not match the input schema.');
        }

        // PHP arrays cannot preserve numeric object keys as strings, which ValidationService requires.
        $input = $arguments->properties();
        if (array_any(array_keys($input), fn(int|string $key): bool => is_int($key))) {
            return $this->error('Tool argument names must be non-numeric strings.');
        }

        $attributes = new ReflectionMethod($tool, 'handle')->getAttributes(Validation::class);
        $rules = $attributes === [] ? [] : $attributes[0]->newInstance()->rules();
        try {
            $validated = $this->validation->validate($input, $rules);
        } catch (ValidationException) {
            // Rules may interpolate labels or supplied values; those diagnostics are not public-safe.
            return $this->error('Tool argument validation failed.');
        } catch (Throwable $throwable) {
            throw new DomainException('Tool validation failed unexpectedly.', 0, $throwable);
        }

        return $this->run($tool, $validated, $info, $progress, $request);
    }

    /**
     * Checks current availability before binding checks, restored arguments or response validation
     */
    private function resumeRequest(McpRequest $request, ?McpProgressReporter $progress): McpResult
    {
        if ($this->interaction === null) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $state = $this->interaction->open($request->parameters()['requestState'] ?? null);
        [$tool, $info] = $this->select($state->get('tool'));
        if (!$tool instanceof McpInteractiveTool) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), null);
        }

        $this->requireCapability($info, $request);
        [$validated, $responses] = $this->interaction->restore($state, $request);

        $terminal = $this->interaction->consume($state, $responses);
        if ($terminal !== null) {
            return $terminal;
        }

        return $this->run(
            $tool,
            $validated,
            $info,
            $progress,
            $request,
            $responses,
            $state->get('mode') === 'confirmation'
        );
    }

    /**
     * Requires form elicitation before validation or entering any interactive Tool branch
     */
    private function requireCapability(McpToolInfo $info, ?McpRequest $request): void
    {
        if (!$info->requiresFormElicitation()) {
            return;
        }

        $elicitation = $request?->metadata()->clientCapabilities()->get('elicitation');
        if (!$elicitation instanceof StrictJson || !$elicitation->get('form') instanceof StrictJson) {
            throw new McpProtocolException(McpProtocolError::missingFormElicitation(), null);
        }

        if ($this->interaction === null) {
            throw new DomainException('Interactive Tools require explicit state protection and caller binding.');
        }
    }

    /**
     * Executes one selected entry inside the existing metadata and diagnostic boundaries
     */
    private function run(
        McpTool $tool,
        ApplicationData $validated,
        McpToolInfo $info,
        ?McpProgressReporter $progress,
        ?McpRequest $request,
        ?McpInputResponses $responses = null,
        bool $confirmation = false
    ): McpResult {
        $reporter = $progress ?? new NullMcpProgressReporter();
        $invoke = fn(): McpResult => $this->execute(
            $tool,
            $validated,
            $info,
            $reporter,
            $request,
            $responses,
            $confirmation
        );
        try {
            return $this->metadata === null ? $invoke() : $this->metadata->invoke($info, $invoke);
        } catch (Throwable $throwable) {
            throw new DomainException('Tool execution failed.', 0, $throwable);
        }
    }

    /**
     * Returns the explicitly registered Tool and its metadata only when currently available
     *
     * @return array{McpTool, McpToolInfo}
     */
    private function select(string $name): array
    {
        $tool = $this->registry->find($name);
        $info = $this->registry->definition($name);
        if ($tool === null || $info === null) {
            throw new McpProtocolException(McpProtocolError::unknownTool(), null);
        }

        try {
            $available = $this->availability->isAvailable($info);
        } catch (Throwable $throwable) {
            throw new DomainException('Tool availability failed.', 0, $throwable);
        }

        if (!$available) {
            throw new McpProtocolException(McpProtocolError::unknownTool(), null);
        }

        return [$tool, $info];
    }

    /**
     * Executes only the selected Tool inside the explicit safe-failure boundary
     */
    private function execute(
        McpTool $tool,
        ApplicationData $validated,
        McpToolInfo $info,
        McpProgressReporter $progress,
        ?McpRequest $request,
        ?McpInputResponses $responses,
        bool $confirmation
    ): McpResult {
        if ($confirmation && $progress->isCancelled()) {
            return $this->error('Tool confirmation cancelled.');
        }

        try {
            if ($responses !== null && $tool instanceof McpInteractiveTool) {
                $output = $tool->resume($validated, $responses, $progress);
            } else {
                $output = $tool->handle($validated, $progress);
            }
        } catch (Throwable $throwable) {
            $message = $this->failures->messageFor($throwable);
            if ($message !== null) {
                return $this->error($message);
            }

            throw new DomainException('Tool invocation failed.', 0, $throwable);
        }

        if ($output instanceof McpInputRequired) {
            if (!$tool instanceof McpInteractiveTool || $request === null || $this->interaction === null) {
                throw new DomainException('Only a configured interactive Tool can request input.');
            }

            return $this->interaction->issue($output, $validated, $request);
        }

        $content = $output->structuredContent()->toData();
        if (!McpToolSchemaValidator::matches($content, $info->outputSchema())) {
            throw new DomainException('Tool output does not match its declared schema.');
        }

        // McpToolOutput is final and derives text from the same isolated JSON snapshot.
        return McpResult::complete([
            'content'           => [['type' => 'text', 'text' => $output->text()]],
            'structuredContent' => $content
        ]);
    }

    /**
     * Creates a complete selected-Tool failure without reflecting untrusted values
     */
    private function error(string $message): McpResult
    {
        return McpResult::complete(['content' => [['type' => 'text', 'text' => $message]], 'isError' => true]);
    }
}
