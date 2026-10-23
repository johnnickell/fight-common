<?php

declare(strict_types=1);

namespace Fight\Common\Application\Execution\Detail\Data;

/**
 * Class CapturedDetail
 *
 * Trusted permitted assembly; a null chunk never fabricates captured bytes
 */
final readonly class CapturedDetail
{
    /**
     * Constructs CapturedDetail
     */
    public function __construct(
        private DetailReference $reference,
        private bool $bodyPermitted,
        private ?ContentChunk $chunk
    ) {
    }

    /**
     * Returns permitted capture metadata and an independently authorized chunk
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference'      => $this->reference->toArray(),
            'body_permitted' => $this->bodyPermitted,
            'chunk'          => $this->chunk?->toArray()
        ];
    }
}
