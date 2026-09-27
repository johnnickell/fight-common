<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpOriginPolicy;

/**
 * Class ExactMcpOriginPolicy
 */
final readonly class ExactMcpOriginPolicy implements McpOriginPolicy
{
    /**
     * @var list<string>
     */
    private array $origins;

    /**
     * Constructs ExactMcpOriginPolicy
     *
     * @phpstan-param list<string> $origins
     */
    public function __construct(array $origins)
    {
        $this->origins = array_map(
            static fn (string $origin): string => McpOrigin::fromString($origin)->toString(),
            $origins
        );
    }

    /**
     * @inheritDoc
     */
    public function allows(string $origin): bool
    {
        return in_array($origin, $this->origins, true);
    }
}
