<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice\Event;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeOccurrence;
use Fight\Common\Domain\Mcp\Notice\RecordedNotice;
use Fight\Common\Domain\Messaging\Event\Event;

/**
 * Class NoticeRecorded
 *
 * Contains safe references only, not notice metadata or a publication receipt
 */
final readonly class NoticeRecorded implements Event
{
    /**
     * Constructs NoticeRecorded
     *
     * @param array<string, int|string> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * Creates a safe fact for a newly committed notice
     */
    public static function fromRecorded(RecordedNotice $recorded): self
    {
        return self::fromArray([
            'version'     => 1,
            'id'          => $recorded->notice()->id(),
            'producer'    => $recorded->notice()->producer(),
            'scope'       => $recorded->notice()->scope(),
            'position'    => $recorded->position(),
            'recorded_at' => $recorded->recordedAt()
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== ['id', 'position', 'producer', 'recorded_at', 'scope', 'version'] || $data['version'] !== 1
            || !is_int($data['position']) || $data['position'] < 1 || $data['position'] > RecordedNotice::MAX_POSITION
            || !is_string($data['recorded_at']) || strlen($data['recorded_at']) !== 27
        ) {
            throw new DomainException('Invalid recorded notice fact.');
        }

        $id = Notice::reference($data['id']);
        $producer = Notice::reference($data['producer']);
        $scope = Notice::reference($data['scope']);
        $time = NoticeOccurrence::fromString($data['recorded_at'])->toString();

        return new self([
            'version'     => 1,
            'id'          => $id,
            'producer'    => $producer,
            'scope'       => $scope,
            'position'    => $data['position'],
            'recorded_at' => $time
        ]);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
