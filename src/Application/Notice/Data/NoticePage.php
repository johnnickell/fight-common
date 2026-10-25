<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\ListNotices;

/**
 * Class NoticePage
 */
final readonly class NoticePage
{
    public const int MAX_BYTES = 262144;

    /**
     * @var list<NoticeView>
     */
    private array $notices;

    /**
     * Constructs NoticePage
     *
     * @phpstan-param array<array-key, NoticeView> $notices
     */
    public function __construct(array $notices, private int $total, private int $page, private int $perPage)
    {
        new ListNotices(NoticeSelection::create([]), $page, $perPage);
        if (!array_is_list($notices) || count($notices) > $perPage || $total < count($notices)) {
            throw new DomainException('Invalid notice page result.');
        }

        $this->notices = array_map(static fn (NoticeView $notice): NoticeView => $notice, $notices);
    }

    /**
     * Returns bounded admitted rows without a continuing access grant
     *
     * @return list<NoticeView>
     */
    public function notices(): array
    {
        return $this->notices;
    }

    /**
     * Returns live full-selection total and page data without a snapshot promise
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notices'  => array_map(static fn (NoticeView $notice): array => $notice->toArray(), $this->notices),
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage
        ];
    }
}
