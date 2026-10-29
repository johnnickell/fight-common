<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;

/**
 * Class NoticeSummary
 */
final readonly class NoticeSummary
{
    public const int MAX_GROUPS = 128;

    /**
     * Constructs NoticeSummary
     *
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * Creates full-selection statistics while retaining only finite dimensions
     *
     * Exceeded dimensions fail the entire read rather than truncating statistics
     *
     * @phpstan-param iterable<NoticeView> $notices
     */
    public static function fromNotices(iterable $notices): self
    {
        $total = 0;
        $topics = [];
        $types = [];
        $audience = ['named' => 0, 'broadcast' => 0, 'unknown' => 0];
        $unknown = ['subject' => 0, 'author' => 0, 'causation' => 0, 'occurrence' => 0];
        $firstRecorded = null;
        $lastRecorded = null;
        $firstOccurred = null;
        $lastOccurred = null;
        foreach ($notices as $view) {
            ++$total;
            $notice = $view->notice();
            $data = $notice->toArray();
            self::countGroup($topics, $data['topic']);
            self::countGroup($types, $data['type']);
            $relations = $notice->relationships()->toArray();
            $recipients = $relations['recipients'];
            $audience[$recipients === null ? 'unknown' : ($recipients === [] ? 'broadcast' : 'named')]++;
            foreach (['subject', 'author'] as $field) {
                if ($relations[$field] === null) {
                    ++$unknown[$field];
                }
            }

            if ($relations['command'] === null && $relations['event'] === null && $relations['action'] === null) {
                ++$unknown['causation'];
            }

            $recorded = $view->recordedAt();
            $firstRecorded = $firstRecorded === null ? $recorded : min($firstRecorded, $recorded);
            $lastRecorded = $lastRecorded === null ? $recorded : max($lastRecorded, $recorded);
            $occurred = $notice->occurrence();
            if ($occurred === null) {
                ++$unknown['occurrence'];
            } else {
                $time = NoticeSelection::instant($occurred->toString());
                $firstOccurred = $firstOccurred === null ? $time : min($firstOccurred, $time);
                $lastOccurred = $lastOccurred === null ? $time : max($lastOccurred, $time);
            }
        }

        ksort($topics, SORT_STRING);
        ksort($types, SORT_STRING);

        return new self([
            'total'             => $total,
            'topics'            => array_values($topics),
            'types'             => array_values($types),
            'audience'          => $audience,
            'unknown'           => $unknown,
            'first_recorded_at' => $firstRecorded,
            'last_recorded_at'  => $lastRecorded,
            'first_occurred_at' => $firstOccurred,
            'last_occurred_at'  => $lastOccurred
        ]);
    }

    /**
     * Returns complete admitted statistics rather than page-local counts
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Counts one notice in a finite exact dimension
     *
     * @param array<string, array{value: string, count: int}> $groups
     */
    private static function countGroup(array &$groups, string $value): void
    {
        $key = 'v:'.$value;
        $groups[$key] ??= ['value' => $value, 'count' => 0];
        ++$groups[$key]['count'];
        if (count($groups) > self::MAX_GROUPS) {
            throw new DomainException('Notice summary dimension bounds exceeded.');
        }
    }
}
