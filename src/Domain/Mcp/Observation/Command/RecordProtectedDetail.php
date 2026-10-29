<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Observation\Command;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Detail\ProtectedContent;
use Fight\Common\Domain\Mcp\Observation\Observation;
use Fight\Common\Domain\Mcp\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RecordProtectedDetail
 *
 * Contains protected bytes; consumers must exclude this Command from safe logging
 */
final readonly class RecordProtectedDetail implements Command
{
    /**
     * Constructs RecordProtectedDetail
     */
    public function __construct(private Observation $observation, private ProtectedContent $content)
    {
        $capture = $observation->detail();
        if ($capture === null) {
            throw new DomainException('Expected a protected detail observation.');
        }

        $capture->validateBody($content->body());
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, ['observation', 'content']);
        if (!is_array($data['observation']) || !is_array($data['content'])) {
            throw new DomainException('Invalid protected detail Command.');
        }

        $observation = Observation::fromArray($data['observation']);
        $capture = $observation->detail();
        if ($capture === null) {
            throw new DomainException('Expected a protected detail observation.');
        }

        return new self($observation, ProtectedContent::fromArray($capture, $data['content']));
    }

    /**
     * Returns safe submitted observation metadata
     */
    public function observation(): Observation
    {
        return $this->observation;
    }

    /**
     * Returns protected submitted content
     */
    public function content(): ProtectedContent
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['observation' => $this->observation->toArray(), 'content' => $this->content->toArray()];
    }
}
