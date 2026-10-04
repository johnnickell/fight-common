<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Attribute;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpToolInfo
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpToolInfo
{
    private StrictJson $inputSchema;
    private StrictJson $outputSchema;

    /**
     * Constructs McpToolInfo
     *
     * @param string               $name
     * @param string               $description
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed> $outputSchema
     */
    public function __construct(
        private string $name,
        private string $description,
        array $inputSchema,
        array $outputSchema,
        private bool $requiresFormElicitation = false
    ) {
        if (preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/D', $name) !== 1) {
            throw new DomainException(
                'A Tool name must contain 1-128 ASCII letters, digits, underscores, dots or hyphens.'
            );
        }

        if (trim($description) === '') {
            throw new DomainException('A Tool description must not be empty.');
        }

        StrictJson::fromData($description);
        $this->inputSchema = McpToolSchema::create($inputSchema, true);
        $this->outputSchema = McpToolSchema::create($outputSchema, false);
    }

    /**
     * Returns the canonical case-sensitive Tool name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the consumer-declared public description
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Returns an isolated JSON value for the declared input schema
     */
    public function inputSchema(): StrictJson
    {
        return $this->inputSchema;
    }

    /**
     * Returns an isolated JSON value for the declared output schema
     */
    public function outputSchema(): StrictJson
    {
        return $this->outputSchema;
    }

    /**
     * Returns whether invocation requires the current client to support form elicitation
     */
    public function requiresFormElicitation(): bool
    {
        return $this->requiresFormElicitation;
    }

    /**
     * Returns the safe discovery definition without a Tool instance or consumer context
     *
     * @return array{name: string, description: string, inputSchema: StrictJson, outputSchema: StrictJson}
     */
    public function toArray(): array
    {
        return [
            'name'         => $this->name,
            'description'  => $this->description,
            'inputSchema'  => $this->inputSchema,
            'outputSchema' => $this->outputSchema
        ];
    }
}
