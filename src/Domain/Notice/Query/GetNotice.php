<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Notice\Query;

use Fight\Common\Domain\Messaging\Query\Query;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class GetNotice
 */
final readonly class GetNotice implements Query
{
    private NoticeSelection $selection;

    /**
     * Constructs GetNotice
     */
    public function __construct(string $scope, string $producer, string $id)
    {
        $this->selection = NoticeSelection::create([Notice::reference($scope)], [
            'producer' => Notice::reference($producer), 'id' => Notice::reference($id)
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        NoticeSelection::structure($data, ['scope', 'producer', 'id']);

        return new self(
            Notice::reference($data['scope']),
            Notice::reference($data['producer']),
            Notice::reference($data['id'])
        );
    }

    /**
     * Returns the exact independently authorized identity selection
     */
    public function selection(): NoticeSelection
    {
        return $this->selection;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return ['scope' => $this->selection->scopes()[0], ...$this->selection->filters()];
    }
}
