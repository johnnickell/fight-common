<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpResult;
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
        private ?McpToolMetadataFilter $metadata = null
    ) {
    }

    /**
     * Invokes one available Tool with validated arguments and a non-streaming reporter
     *
     * Availability precedes argument inspection. Unexpected Tool exceptions cannot impersonate
     * protocol failures. The caller's central diagnostic boundary records them exactly once.
     */
    public function invoke(string $name, mixed $arguments): McpResult
    {
        [$tool, $info] = $this->select($name);

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

        $invoke = fn(): McpResult => $this->execute($tool, $validated, $info);
        try {
            return $this->metadata === null ? $invoke() : $this->metadata->invoke($info, $invoke);
        } catch (Throwable $throwable) {
            throw new DomainException('Tool execution failed.', 0, $throwable);
        }
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
    private function execute(McpTool $tool, ApplicationData $validated, McpToolInfo $info): McpResult
    {
        try {
            $output = $tool->handle($validated, new NullMcpProgressReporter());
        } catch (Throwable $throwable) {
            $message = $this->failures->messageFor($throwable);
            if ($message !== null) {
                return $this->error($message);
            }

            throw new DomainException('Tool invocation failed.', 0, $throwable);
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
