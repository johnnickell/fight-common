<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Execution\Dbal;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Detail\ProtectedContentRepository;
use Fight\Common\Domain\Execution\Observation\Observation;

/**
 * Class DbalProtectedContentRepository
 *
 * Requires trusted storage composition; it is not a protected-read authorization API
 */
final readonly class DbalProtectedContentRepository implements ProtectedContentRepository
{
    /**
     * Constructs DbalProtectedContentRepository
     */
    public function __construct(private DbalRecordingTransaction $transaction)
    {
    }

    /**
     * @inheritDoc
     */
    public function getFor(Observation $detail): ?ProtectedContent
    {
        $capture = $detail->detail();
        if ($capture === null) {
            throw new DomainException('Expected a protected detail observation.');
        }

        $row = $this->transaction->connection()->fetchAssociative(
            'SELECT payload FROM fight_execution_contents WHERE producer_id = ? AND observation_id = ?',
            [$detail->producerId(), $detail->id()->toString()]
        );
        if ($row === false) {
            return null;
        }

        $data = json_decode($row['payload'], true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new DomainException('Invalid stored protected content.');
        }

        return ProtectedContent::fromArray($capture, $data);
    }

    /**
     * @inheritDoc
     */
    public function add(Observation $detail, ProtectedContent $content): void
    {
        $this->transaction->requireOwnedTransaction();
        $capture = $detail->detail();
        if ($capture === null) {
            throw new DomainException('Expected a protected detail observation.');
        }

        $capture->validateBody($content->body());
        $this->transaction->connection()->insert('fight_execution_contents', [
            'producer_id'    => $detail->producerId(),
            'observation_id' => $detail->id()->toString(),
            'payload'        => json_encode($content->toArray(), JSON_THROW_ON_ERROR)
        ]);
    }

    /**
     * @inheritDoc
     */
    public function removeFor(Observation $detail): void
    {
        $this->transaction->requireOwnedTransaction();
        if ($detail->detail() === null) {
            throw new DomainException('Expected a protected detail observation.');
        }

        $this->transaction->connection()->delete('fight_execution_contents', [
            'producer_id'    => $detail->producerId(),
            'observation_id' => $detail->id()->toString()
        ]);
    }
}
