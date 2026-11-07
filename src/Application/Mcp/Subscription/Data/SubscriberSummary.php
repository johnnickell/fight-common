<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Data;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class SubscriberSummary
 */
final readonly class SubscriberSummary
{
    /**
     * Constructs SubscriberSummary
     *
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * Creates full-selection counts with fixed state dimensions and bounded distinct-identity memory
     *
     * @phpstan-param iterable<SubscriberView> $subscribers
     */
    public static function fromSubscribers(iterable $subscribers, SubscriberReadScope $scope): self
    {
        $total = 0;
        $identities = [];
        $states = ['recently_observed' => 0, 'disconnected' => 0, 'stale_uncertain' => 0];
        $oldest = null;
        $latest = null;
        foreach ($subscribers as $view) {
            if (++$total > 8192) {
                throw new DomainException('Subscriber summary scan bound exceeded.');
            }

            $identities[$view->subscriber()] = true;
            ++$states[$view->state()];
            $observed = $view->toArray()['observed_at'];
            $oldest = $oldest === null ? $observed : min($oldest, $observed);
            $latest = $latest === null ? $observed : max($latest, $observed);
        }

        return new self([
            'scope'              => $scope->toArray(),
            'subscriptions'      => $total,
            'subscriber_ids'     => count($identities),
            'states'             => $states,
            'oldest_observed_at' => $oldest,
            'latest_observed_at' => $latest
        ]);
    }

    /**
     * Returns admitted counts, never page-local totals, global activity or receipt evidence
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
