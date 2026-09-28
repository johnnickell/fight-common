<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Messaging;

use Fight\Common\Application\Mcp\McpResult;
use Fight\Common\Application\Mcp\Tool\McpToolInfo;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Meta;
use Fight\Common\Domain\Messaging\Query\QueryMessage;

/**
 * Class McpToolMetadataFilter
 */
final class McpToolMetadataFilter implements CommandFilter, QueryFilter
{
    private ?Meta $metadata = null;
    private bool $active = false;

    /**
     * Constructs McpToolMetadataFilter
     *
     * Share this request-scoped instance between the invoker and existing command/query pipelines.
     * Explicitly allowlist consumer audit field names; do not share it across concurrent requests.
     *
     * @phpstan-param list<string> $allowedFields
     */
    public function __construct(
        private readonly ?McpToolAuditMetadata $audit = null,
        private readonly array $allowedFields = []
    ) {
        foreach ($allowedFields as $field) {
            if (
                preg_match('/\A[a-z][a-z0-9.-]*\/[A-Za-z][A-Za-z0-9_.-]*\z/D', $field) !== 1
                || str_starts_with($field, 'fight.mcp/')
                || preg_match(
                    '/credential|password|secret|token|authorization|permission|argument|header|payload/i',
                    $field
                ) === 1
            ) {
                throw new DomainException('Tool audit fields must be namespaced non-secret identifiers.');
            }
        }
    }

    /**
     * Invokes a validated Tool inside one isolated metadata scope
     *
     * @phpstan-param callable(): McpResult $invoke
     */
    public function invoke(McpToolInfo $tool, callable $invoke): McpResult
    {
        if ($this->active) {
            throw new DomainException('A Tool metadata scope cannot be nested or shared concurrently.');
        }

        $this->active = true;
        try {
            $fields = $this->audit?->fieldsFor($tool) ?? [];
            foreach ($fields as $name => $value) {
                if (
                    !in_array($name, $this->allowedFields, true)
                    || !is_scalar($value)
                    || (is_float($value) && !is_finite($value))
                    || (is_string($value)
                        && (strlen($value) > 256 || preg_match('/[\x00-\x1f\x7f]/u', $value) !== 0))
                ) {
                    throw new DomainException(
                        'Tool audit metadata must use allowlisted names and bounded scalar values.'
                    );
                }
            }

            $this->metadata = Meta::create([
                'fight.mcp/tool'        => $tool->name(),
                'fight.mcp/correlation' => bin2hex(random_bytes(16)),
                ...$fields
            ]);

            return $invoke();
        } finally {
            $this->metadata = null;
            $this->active = false;
        }
    }

    /**
     * Processes an envelope with metadata only while the corresponding validated Tool is executing
     */
    public function process(CommandMessage|QueryMessage $message, callable $next): void
    {
        $next($this->metadata === null ? $message : $message->mergeMeta($this->metadata));
    }
}
