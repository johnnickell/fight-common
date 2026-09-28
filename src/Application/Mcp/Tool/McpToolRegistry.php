<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Application\Mcp\Tool\Interaction\McpInteractiveTool;
use Fight\Common\Domain\Exception\DomainException;
use ReflectionMethod;
use Throwable;

/**
 * Class McpToolRegistry
 */
final readonly class McpToolRegistry
{
    /**
     * @var array<string, McpTool>
     */
    private array $tools;
    /**
     * @var array<string, McpToolInfo>
     */
    private array $definitions;
    /**
     * @var array<string, list<McpMirrorDeclaration>>
     */
    private array $mirrors;

    /**
     * Constructs McpToolRegistry
     *
     * @param array<mixed> $tools
     */
    public function __construct(array $tools)
    {
        $registered = [];
        $definitions = [];
        $mirrors = [];
        foreach ($tools as $tool) {
            if (!$tool instanceof McpTool) {
                throw new DomainException('Only explicitly supplied McpTool implementations can be registered.');
            }

            $attributes = new ReflectionMethod($tool, 'handle')->getAttributes(McpToolInfo::class);
            if (count($attributes) !== 1) {
                throw new DomainException('Every Tool handle method must declare exactly one McpToolInfo attribute.');
            }

            try {
                $info = $attributes[0]->newInstance();
            } catch (Throwable $exception) {
                throw new DomainException('A Tool has invalid McpToolInfo metadata.', 0, $exception);
            }

            if (($tool instanceof McpInteractiveTool) !== $info->requiresFormElicitation()) {
                throw new DomainException('Interactive Tools must explicitly require form elicitation in metadata.');
            }

            $key = 'tool:'.$info->name();
            if (isset($registered[$key])) {
                throw new DomainException('Tool canonical names must be unique.');
            }

            $registered[$key] = $tool;
            $definitions[$key] = $info;
            $mirrors[$key] = McpToolMirrors::declarations($info->inputSchema());
        }

        $this->tools = $registered;
        $this->definitions = $definitions;
        $this->mirrors = $mirrors;
    }

    /**
     * Returns an explicitly registered Tool without authorizing or invoking it
     */
    public function find(string $name): ?McpTool
    {
        return $this->tools['tool:'.$name] ?? null;
    }

    /**
     * Returns immutable registered metadata without making an availability decision
     */
    public function definition(string $name): ?McpToolInfo
    {
        return $this->definitions['tool:'.$name] ?? null;
    }

    /**
     * Returns composition-validated mirrors without authorizing the named Tool
     *
     * @return list<McpMirrorDeclaration>
     */
    public function mirrorsFor(string $name): array
    {
        return $this->mirrors['tool:'.$name] ?? [];
    }

    /**
     * Returns current available metadata in deterministic canonical-name order
     *
     * @return list<McpToolInfo>
     */
    public function available(McpToolAvailability $availability): array
    {
        $definitions = array_values(array_filter($this->definitions, $availability->isAvailable(...)));
        usort($definitions, fn(McpToolInfo $left, McpToolInfo $right): int => strcmp($left->name(), $right->name()));

        return $definitions;
    }
}
