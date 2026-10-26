<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Subscription;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Common\Domain\Notice\NoticeSelection;

/**
 * Class SubscriberSelection
 *
 * @phpstan-type IdentityFilter 'subscriber'|'topic'|'registration_id'|'state'
 * @phpstan-type ProtocolFilter 'requested_filter'|'accepted_filter'|'requested_resource'|'accepted_resource'
 * @phpstan-type SubscriberFilter IdentityFilter|ProtocolFilter|'opened_from'|'opened_until'
 */
final readonly class SubscriberSelection
{
    /**
     * Constructs SubscriberSelection
     *
     * @phpstan-param list<string> $scopes
     * @phpstan-param array<SubscriberFilter, int|string> $filters
     */
    private function __construct(private array $scopes, private array $filters)
    {
    }

    /**
     * Creates a finite selection of complete registrations without granting inspection
     *
     * @param array<mixed> $scopes
     * @param array<array-key, mixed> $filters
     */
    public static function create(array $scopes, array $filters = []): self
    {
        $scopes = NoticeSelection::create($scopes)->scopes();
        $safe = [];
        foreach ($filters as $field => $value) {
            if (in_array($field, ['subscriber', 'topic'], true)) {
                $safe[$field] = Notice::reference($value);
            } elseif (
                $field === 'registration_id' && is_string($value) && preg_match('/\A[0-9a-f]{32}\z/D', $value)
            ) {
                $safe[$field] = $value;
            } elseif (
                $field === 'state' && in_array($value, ['recently_observed', 'disconnected', 'stale_uncertain'], true)
            ) {
                $safe[$field] = $value;
            } elseif (in_array($field, ['requested_filter', 'accepted_filter'], true)) {
                $safe[$field] = Notice::reference($value);
            } elseif (
                in_array($field, ['requested_resource', 'accepted_resource'], true)
                && is_string($value) && strlen($value) <= 2048 && preg_match('//u', $value) === 1
                && preg_match('/\A[^\x00-\x20\x7f]+\z/D', $value) === 1
            ) {
                $safe[$field] = $value;
            } elseif (
                in_array($field, ['opened_from', 'opened_until'], true)
                && is_int($value) && $value >= 1 && $value <= 2147483647
            ) {
                $safe[$field] = $value;
            } else {
                throw new DomainException('Invalid subscriber selection.');
            }
        }

        if (isset($safe['opened_from'], $safe['opened_until']) && $safe['opened_from'] > $safe['opened_until']) {
            throw new DomainException('Reversed subscriber time range.');
        }

        ksort($safe, SORT_STRING);

        return new self($scopes, $safe);
    }

    /**
     * Creates a selection from its exact representation
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        NoticeSelection::structure($data, ['scopes', 'filters']);
        if (!is_array($data['scopes']) || !is_array($data['filters'])) {
            throw new DomainException('Invalid subscriber selection.');
        }

        return self::create($data['scopes'], $data['filters']);
    }

    /**
     * Returns selected notice scopes, all of which must cover a returned registration
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Returns exact conjunctive filters
     *
     * @return array<SubscriberFilter, int|string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Returns the selection without authority
     *
     * @return array{scopes: list<string>, filters: array<string, int|string>}
     */
    public function toArray(): array
    {
        return ['scopes' => $this->scopes, 'filters' => $this->filters];
    }
}
