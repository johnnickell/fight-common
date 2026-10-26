<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Subscription\Service;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Subscription\SubscriberSelection;

/**
 * Class SubscriberAccess
 *
 * Trusted current grants for every identity, opening filter and reference in each admitted registration
 */
final readonly class SubscriberAccess
{
    /**
     * Constructs SubscriberAccess
     *
     * @phpstan-param list<string>|null $registrations
     */
    private function __construct(private SubscriberSelection $selection, private ?array $registrations)
    {
    }

    /**
     * Creates complete-metadata grants with optional exact registration restrictions
     *
     * Empty scopes or an empty registration list deny everything; null registrations grants the selected scope
     * Consumers must exclude entire rows when any identity, requested filter or reference cannot be disclosed
     *
     * @param array<mixed> $scopes
     * @param array<mixed>|null $registrations
     */
    public static function create(array $scopes, ?array $registrations = null): self
    {
        if ($registrations !== null) {
            if (!array_is_list($registrations) || count($registrations) > 4096) {
                throw new DomainException('Invalid subscriber grants.');
            }

            foreach ($registrations as $id) {
                SubscriberSelection::create([], ['registration_id' => $id]);
            }
        }

        return new self(SubscriberSelection::create($scopes), $registrations);
    }

    /**
     * Returns whether this invocation admits any observations
     */
    public function isDenied(): bool
    {
        return $this->selection->scopes() === [] || $this->registrations === [];
    }

    /**
     * Returns whether all metadata in this registration is currently admitted
     */
    public function allows(string $registrationId, NoticeSelection $selection): bool
    {
        return $selection->scopes() !== [] && array_diff($selection->scopes(), $this->selection->scopes()) === []
            && ($this->registrations === null || in_array($registrationId, $this->registrations, true));
    }
}
