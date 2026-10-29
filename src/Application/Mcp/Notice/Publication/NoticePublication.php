<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Notice\Publication;

use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use LogicException;
use Throwable;

/**
 * Class NoticePublication
 *
 * Reconstructs committed content through shared inspection, never caller-asserted saved payloads
 */
final readonly class NoticePublication
{
    /**
     * Constructs NoticePublication
     */
    public function __construct(
        private QueryBus $queries,
        private NoticePublicationPolicy $policy,
        private NoticePublicationDiagnostics $diagnostics,
        private ?Publisher $publicPublisher = null,
        private ?PrivatePublisher $privatePublisher = null
    ) {
    }

    /**
     * Handles one bounded attempt with fresh read and separate publication authorization
     *
     * The shared GetNotice handler and committed reader must be composed on the QueryBus
     * Success means publisher return only; response loss and repeated attempts may duplicate sends
     */
    public function publish(GetNotice $identity): NoticePublicationStatus
    {
        try {
            $view = $this->queries->fetch($identity);
            if ($view === null) {
                return NoticePublicationStatus::NOT_AVAILABLE;
            }

            if (!$view instanceof NoticeView) {
                throw new LogicException('Notice publication requires shared notice inspection.');
            }

            try {
                $grant = $this->policy->currentGrant($view);
                if ($grant === null) {
                    return NoticePublicationStatus::DENIED;
                }

                $message = $grant->message($view);
            } catch (DomainException $failure) {
                $this->report($failure);

                return NoticePublicationStatus::INVALID;
            }

            if ($grant->isPrivate()) {
                if ($this->privatePublisher === null) {
                    return NoticePublicationStatus::UNSUPPORTED;
                }

                $this->privatePublisher->pushPrivate($grant->topic(), $message);
            } else {
                if ($this->publicPublisher === null) {
                    return NoticePublicationStatus::UNSUPPORTED;
                }

                $this->publicPublisher->push($grant->topic(), $message);
            }

            return NoticePublicationStatus::PUBLISHED;
        } catch (Throwable $throwable) {
            $this->report($throwable);

            return NoticePublicationStatus::FAILED;
        }
    }

    /**
     * Records private failures without letting diagnostic failure undo data or claim success
     */
    private function report(Throwable $failure): void
    {
        try {
            $this->diagnostics->record($failure);
        } catch (Throwable) {
            // The failed outcome remains observable even if the private diagnostic sink is unavailable.
        }
    }
}
