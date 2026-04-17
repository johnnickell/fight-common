<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Value\Internet;

use Override;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class Url
 */
final class Url extends Uri
{
    protected const DEFAULT_PORTS = [
        'http'  => 80,
        'https' => 443
    ];

    /**
     * Normalizes the query
     *
     * Sorts query by key and removes values without keys.
     *
     * @throws DomainException When the query is invalid
     */
    #[Override]
    protected static function normalizeQuery(?string $query): ?string
    {
        if (null === $query) {
            return null;
        }

        if ('' === $query) {
            return '';
        }

        $parts = [];
        $order = [];

        // sort query params by key and remove missing keys
        foreach (explode('&', $query) as $param) {
            if ('' === $param || '=' === $param[0]) {
                continue;
            }
            $parts[] = $param;
            $kvp = explode('=', $param, 2);
            $order[] = $kvp[0];
        }

        array_multisort($order, SORT_ASC, $parts);

        return parent::normalizeQuery(implode('&', $parts));
    }
}
