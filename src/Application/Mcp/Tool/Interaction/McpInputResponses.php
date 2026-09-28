<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool\Interaction;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpInputResponses
 */
final readonly class McpInputResponses
{
    /**
     * Constructs McpInputResponses
     *
     * @param array<string, McpInputResponse> $responses
     */
    private function __construct(private array $responses)
    {
    }

    /**
     * Validates all response envelopes and keys before any accept-only content
     *
     * @param mixed $data
     * @param array<string, McpInputRequest> $requests
     *
     * @internal Used only after current availability and state binding checks
     */
    public static function validate(mixed $data, array $requests): self
    {
        $data = StrictJson::fromData($data);
        if (!$data->isObject()) {
            throw new DomainException('Invalid interaction response.');
        }

        $values = $data->properties();
        if (count($values) !== count($requests) || array_diff(array_keys($values), array_keys($requests)) !== []) {
            throw new DomainException('Invalid interaction response.');
        }

        foreach ($values as $value) {
            if (
                !$value instanceof StrictJson
                || array_diff(array_keys($value->properties()), ['action', 'content']) !== []
                || !in_array($value->get('action'), ['accept', 'decline', 'cancel'], true)
                || ($value->get('action') === 'accept' && !$value->get('content') instanceof StrictJson)
                || ($value->get('action') !== 'accept' && $value->has('content'))
            ) {
                throw new DomainException('Invalid interaction response.');
            }
        }

        $responses = [];
        foreach ($requests as $key => $request) {
            $value = $values[$key];
            $content = $value->get('action') === 'accept' ? $request->validate($value->get('content')) : null;
            $responses[$key] = McpInputResponse::validated($value->get('action'), $content);
        }

        return new self($responses);
    }

    /**
     * Returns the response for a known input request key
     */
    public function get(string $key): McpInputResponse
    {
        return $this->responses[$key] ?? throw new DomainException('Unknown input response key.');
    }

    /**
     * Returns the validated response map
     *
     * @return array<string, McpInputResponse>
     */
    public function toArray(): array
    {
        return $this->responses;
    }
}
