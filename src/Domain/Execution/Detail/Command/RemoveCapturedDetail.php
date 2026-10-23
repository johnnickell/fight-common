<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Execution\Detail\Command;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\DetailSelection;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Value\ObservationFields;
use Fight\Common\Domain\Messaging\Command\Command;

/**
 * Class RemoveCapturedDetail
 *
 * Selects exactly one protected row, retaining safe identity and relationships
 */
final readonly class RemoveCapturedDetail implements Command
{
    /**
     * Constructs RemoveCapturedDetail
     */
    public function __construct(
        private DetailSelection $selection,
        private ObservationId $detailId,
        private DetailKind $kind
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        ObservationFields::structure($data, ['selection', 'detail_id', 'kind']);
        if (
            !is_array($data['selection']) || !is_string($data['detail_id'])
            || preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $data['detail_id']) !== 1
            || !is_string($data['kind'])
        ) {
            throw new DomainException('Invalid captured detail removal.');
        }

        $kind = DetailKind::tryFrom($data['kind']);
        if ($kind === null) {
            throw new DomainException('Unsupported captured detail kind.');
        }

        return new self(
            DetailSelection::fromArray($data['selection']),
            ObservationId::fromString($data['detail_id']),
            $kind
        );
    }

    /**
     * Returns the exact activity selection without authority
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
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'selection' => $this->selection->toArray(),
            'detail_id' => $this->detailId->toString(),
            'kind'      => $this->kind->value
        ];
    }
}
