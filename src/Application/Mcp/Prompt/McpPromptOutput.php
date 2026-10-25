<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

use Fight\Common\Application\Mcp\Resource\McpResourceInfo;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpPromptOutput
 */
final readonly class McpPromptOutput implements Arrayable
{
    /**
     * Constructs McpPromptOutput
     */
    private function __construct(private StrictJson $data)
    {
    }

    /**
     * Creates complete user or assistant text and Resource-link messages without resolving links
     *
     * Empty messages are a legitimate complete result. Unsupported fields, roles and blocks reject.
     * Resource metadata uses the existing Resource contract; its disclosure is the consumer's responsibility.
     *
     * @param array<string, mixed> $output
     */
    public static function fromArray(array $output): self
    {
        $data = StrictJson::fromObject($output, maxDepth: 40);
        $messages = $data->get('messages');
        if (
            array_diff(array_keys($output), ['description', 'messages']) !== []
            || ($data->has('description') && !is_string($data->get('description')))
            || !is_array($messages) || count($messages) > 128
        ) {
            throw new DomainException('Invalid complete Prompt output.');
        }

        foreach ($messages as $message) {
            if (
                !$message instanceof StrictJson
                || array_diff(array_keys($message->properties()), ['role', 'content']) !== []
                || !in_array($message->get('role'), ['user', 'assistant'], true)
                || !$message->get('content') instanceof StrictJson
            ) {
                throw new DomainException('Invalid Prompt message.');
            }

            $content = $message->get('content');
            if ($content->get('type') === 'resource_link') {
                McpResourceInfo::fromArray(array_diff_key($content->properties(), ['type' => true]));

                continue;
            }

            if (
                $content->get('type') !== 'text'
                || array_diff(array_keys($content->properties()), ['type', 'text']) !== []
                || !is_string($content->get('text')) || strlen($content->get('text')) > 65536
            ) {
                throw new DomainException('Unsupported or oversized Prompt content.');
            }
        }

        if (strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 262144) {
            throw new DomainException('Prompt output exceeds its encoded byte budget.');
        }

        return new self($data);
    }

    /**
     * Returns complete immutable protocol output without an envelope
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data->properties();
    }
}
