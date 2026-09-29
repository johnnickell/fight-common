<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth\OAuth;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class OAuthScopeSet
 */
final readonly class OAuthScopeSet
{
    /**
     * Constructs OAuthScopeSet
     *
     * @phpstan-param list<string> $values
     */
    private function __construct(public array $values)
    {
    }

    /**
     * Creates a canonical set of case-sensitive RFC 6749 scope tokens
     *
     * @phpstan-param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        if (!array_is_list($values)) {
            throw new DomainException('OAuth scopes must be a list.');
        }

        $seen = [];
        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/\A[\x21\x23-\x5b\x5d-\x7e]+\z/', $value) !== 1) {
                throw new DomainException('Invalid OAuth scope.');
            }

            if (in_array($value, $seen, true)) {
                throw new DomainException('Duplicate OAuth scope.');
            }

            $seen[] = $value;
        }

        sort($seen, SORT_STRING);

        return new self($seen);
    }

    /**
     * Returns whether every required scope is in this trusted effective set
     */
    public function includes(self $required): bool
    {
        return array_diff($required->values, $this->values) === [];
    }
}
