<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Execution\Detail\Data;

/**
 * Class MessagePage
 */
final readonly class MessagePage
{
    /**
     * Constructs MessagePage
     *
     * @phpstan-param list<CapturedDetail> $messages
     */
    public function __construct(private array $messages, private int $total, private int $page, private int $perPage)
    {
    }

    /**
     * Returns bounded permitted message results
     *
     * @return list<CapturedDetail>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Returns live pagination metadata and bounded message chunks
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'messages' => array_map(static fn (CapturedDetail $message): array => $message->toArray(), $this->messages),
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage
        ];
    }
}
