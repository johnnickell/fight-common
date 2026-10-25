<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription;

use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Domain\Value\Basic\StrictJson;
use Uri\Rfc3986\Uri;

/**
 * Class McpSubscriptionFilter
 */
final readonly class McpSubscriptionFilter
{
    /**
     * Constructs McpSubscriptionFilter
     *
     * @param array<string, mixed> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * Creates a finite explicit protocol filter without resolving any target
     */
    public static function fromRequest(McpRequest $request): self
    {
        $parameters = $request->parameters();
        $value = $parameters['notifications'] ?? null;
        if (array_keys($parameters) !== ['notifications'] || !$value instanceof StrictJson || !$value->isObject()) {
            throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
        }

        $values = $value->properties();
        foreach ($values as $name => $item) {
            if ($name === 'resourceSubscriptions') {
                if (!is_array($item) || !array_is_list($item) || count($item) > 16) {
                    throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
                }

                foreach ($item as $uri) {
                    if (
                        !is_string($uri) || strlen($uri) > 2048 || preg_match('/[\x00-\x20\x7f]/', $uri) === 1
                        || Uri::parse($uri)?->getScheme() === null
                    ) {
                        throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
                    }
                }

                $values[$name] = array_values(array_unique($item));
            } elseif (
                !in_array($name, ['toolsListChanged', 'resourcesListChanged', 'promptsListChanged'], true)
                || !is_bool($item)
            ) {
                throw new McpProtocolException(McpProtocolError::invalidParams(), $request->id());
            }
        }

        return new self($values);
    }

    /**
     * Returns requested values, retaining false and empty distinctions for technical tracking
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Returns only enabled requested cases currently permitted for disclosure
     *
     * @phpstan-param list<string> $supported
     *
     * @return array<string, mixed>
     */
    public function accepted(array $supported, McpSubscriptionAdmission $admission, string $subscriber): array
    {
        $accepted = [];
        foreach ($this->values as $filter => $value) {
            if (!in_array($filter, $supported, true)) {
                continue;
            }

            if ($filter === 'resourceSubscriptions') {
                $uris = array_values(array_filter(
                    $value,
                    static fn (string $uri): bool => $admission->allows($subscriber, $filter, $uri, null)
                ));
                if ($uris !== []) {
                    $accepted[$filter] = $uris;
                }
            } elseif ($value === true && $admission->allows($subscriber, $filter, null, null)) {
                $accepted[$filter] = true;
            }
        }

        return $accepted;
    }
}
