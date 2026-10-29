<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Detail\Query;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Observation\ObservationId;
use Fight\Common\Domain\Mcp\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Query\Query;

/**
 * Class GetCapturedDetail
 */
final readonly class GetCapturedDetail implements Query
{
    /**
     * Constructs GetCapturedDetail
     */
    public function __construct(
        private DetailSelection $selection,
        private ObservationId $detailId,
        private DetailKind $kind,
        private int $offset = 0,
        private int $length = 4096
    ) {
        if ($offset < 0 || $offset > 65536 || $length < 1 || $length > 4096) {
            throw new DomainException('Invalid captured detail byte range.');
        }
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, ['selection', 'detail_id', 'kind', 'offset', 'length']);
        if (
            !is_array($data['selection']) || !is_string($data['detail_id'])
            || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $data['detail_id']) !== 1
            || !is_string($data['kind']) || !is_int($data['offset']) || !is_int($data['length'])
        ) {
            throw new DomainException('Invalid captured detail request.');
        }

        $kind = DetailKind::tryFrom($data['kind']);
        if ($kind === null) {
            throw new DomainException('Unsupported captured detail kind.');
        }

        return new self(
            DetailSelection::fromArray($data['selection']),
            ObservationId::fromString($data['detail_id']),
            $kind,
            $data['offset'],
            $data['length']
        );
    }

    /**
     * Returns the exact activity selection
     */
    public function selection(): DetailSelection
    {
        return $this->selection;
    }

    /**
     * Returns the selected detail identity
     */
    public function detailId(): ObservationId
    {
        return $this->detailId;
    }

    /**
     * Returns the required content kind
     */
    public function kind(): DetailKind
    {
        return $this->kind;
    }

    /**
     * Returns the byte offset within this captured body or output segment
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns the maximum raw bytes for this request
     */
    public function length(): int
    {
        return $this->length;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'selection' => $this->selection->toArray(),
            'detail_id' => $this->detailId->toString(),
            'kind'      => $this->kind->value,
            'offset'    => $this->offset,
            'length'    => $this->length
        ];
    }
}
