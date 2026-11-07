<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Data;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;
use Fight\Common\Domain\Mcp\Subscription\SubscriberSelection;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class SubscriberView
 *
 * Complete inspection metadata, never tracking ownership, credentials or a transport request identity
 */
final readonly class SubscriberView
{
    /**
     * Constructs SubscriberView
     */
    public function __construct(
        private string $registrationId,
        private string $subscriber,
        private NoticeSelection $noticeSelection,
        private StrictJson $requested,
        private StrictJson $accepted,
        private int $openedAt,
        private int $observedAt,
        private int $expiresAt,
        private int $closedAt,
        private int $queriedAt
    ) {
        Notice::reference($subscriber);
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $registrationId) !== 1
            || !$requested->isObject() || !$accepted->isObject()
            || $openedAt < 1 || $observedAt < $openedAt || $expiresAt <= $openedAt
            || $expiresAt > 2147483647 || $observedAt > 2147483647
            || $closedAt < 0 || ($closedAt !== 0 && $closedAt !== $observedAt)
            || $queriedAt < 1 || $queriedAt > 2147483647
            || strlen($requested->toString()) + strlen($accepted->toString()) > 65536
        ) {
            throw new DomainException('Invalid subscriber observation.');
        }
    }

    /**
     * Returns an immutable technical registration identity
     */
    public function registrationId(): string
    {
        return $this->registrationId;
    }

    /**
     * Returns the trusted opening identity, not continuing permission
     */
    public function subscriber(): string
    {
        return $this->subscriber;
    }

    /**
     * Returns the full opening notice scope and filters without a current notice-read grant
     */
    public function noticeSelection(): NoticeSelection
    {
        return $this->noticeSelection;
    }

    /**
     * Returns only the state established by observed lifecycle evidence
     */
    public function state(): string
    {
        if ($this->observedAt > $this->queriedAt) {
            return 'stale_uncertain';
        }

        if ($this->closedAt !== 0) {
            return 'disconnected';
        }

        return $this->expiresAt > $this->queriedAt ? 'recently_observed' : 'stale_uncertain';
    }

    /**
     * Returns whether the whole observation matches a bounded caller selection
     */
    public function matches(SubscriberSelection $selection): bool
    {
        $scopes = $this->noticeSelection->scopes();
        if ($scopes === [] || array_diff($scopes, $selection->scopes()) !== []) {
            return false;
        }

        foreach ($selection->filters() as $field => $value) {
            $matches = match ($field) {
                'registration_id'    => $this->registrationId === $value,
                'subscriber'         => $this->subscriber === $value,
                'state'              => $this->state() === $value,
                'topic'              => ($this->noticeSelection->filters()['topic'] ?? null) === $value,
                'opened_from'        => $this->openedAt >= $value,
                'opened_until'       => $this->openedAt < $value,
                'requested_filter'   => $this->requested->has($value),
                'accepted_filter'    => $this->accepted->has($value),
                'requested_resource' => in_array($value, $this->requested->get('resourceSubscriptions') ?? [], true),
                'accepted_resource'  => in_array($value, $this->accepted->get('resourceSubscriptions') ?? [], true)
            };
            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns complete permitted metadata without implying delivery, receipt or processing
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'registration_id'  => $this->registrationId,
            'subscriber'       => $this->subscriber,
            'notice_selection' => $this->noticeSelection->toArray(),
            'requested'        => $this->requested,
            'accepted'         => $this->accepted,
            'opened_at'        => $this->openedAt,
            'observed_at'      => $this->observedAt,
            'expires_at'       => $this->expiresAt,
            'closed_at'        => $this->closedAt === 0 ? null : $this->closedAt,
            'state'            => $this->state()
        ];
    }
}
