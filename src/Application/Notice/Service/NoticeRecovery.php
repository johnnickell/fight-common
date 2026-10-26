<?php

declare(strict_types=1);

namespace Fight\Common\Application\Notice\Service;

use Fight\Common\Application\Notice\Data\NoticeRecoveryBatch;
use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Query\RecoverNotices;
use Fight\Common\Domain\Notice\RecordedNotice;

/**
 * Class NoticeRecovery
 */
final readonly class NoticeRecovery
{
    /**
     * Constructs NoticeRecovery
     */
    public function __construct(
        private NoticeStore $store,
        private NoticeContinuationProtector $protector,
        private int $ttl = 300,
        private int $maxResultBytes = 65536
    ) {
        if ($ttl < 1 || $ttl > 900 || $maxResultBytes < 2048 || $maxResultBytes > 262144) {
            throw new DomainException('Invalid notice recovery configuration.');
        }
    }

    /**
     * Returns coherently assessed authorized recovery without business mutation or receipt recording
     *
     * A missing trusted caller rejects before storage; unproven continuity always stays uncertain
     */
    public function recover(RecoverNotices $query, NoticeAccess $access): NoticeRecoveryBatch
    {
        $identity = $access->recoveryIdentity();
        if ($identity === null) {
            throw new DomainException('Notice recovery requires trusted caller context.');
        }

        $binding = hash('sha256', json_encode([
            $query->selection()->toArray(), $query->batchSize(), $query->scanLimit(),
            $access->selection()->toArray(), $identity->binding(), $this->ttl, $this->maxResultBytes
        ], JSON_THROW_ON_ERROR));
        $state = $query->continuation() === null ? null : $this->restore($query->continuation(), $binding);
        $position = $state['position'] ?? 0;
        $scopes = array_values(array_intersect($query->selection()->scopes(), $access->selection()->scopes()));
        $window = $this->store->readWindow($scopes, $position, $query->scanLimit());
        if ($state !== null && ($state['namespace'] !== $window->namespace() || $position > $window->highWater())) {
            throw new DomainException('Notice recovery requires restart.');
        }

        $loss = ($state['loss'] ?? false) || !$window->hasUnbrokenHistoryAfter($window->namespace(), $position);
        $rows = $window->notices();
        if ($query->startAtCurrent()) {
            // Opening a live follow explicitly excludes history, including earlier known loss.
            $position = $window->highWater();
            $loss = false;
            $rows = [];
        }

        $notices = [];
        $examined = 0;
        foreach ($rows as $recorded) {
            if (
                $recorded->position() <= $position || $recorded->position() > $window->highWater()
                || !in_array($recorded->notice()->scope(), $scopes, true)
            ) {
                throw new DomainException('Inconsistent notice recovery window.');
            }

            if (
                $access->selection()->matches($recorded->notice(), $recorded->recordedAt())
                && $query->selection()->matches($recorded->notice(), $recorded->recordedAt())
            ) {
                if (count($notices) === $query->batchSize()) {
                    break;
                }

                $notices[] = new NoticeView($recorded->notice(), $recorded->recordedAt());
            }

            $position = $recorded->position();
            ++$examined;
        }

        $exhausted = $examined === count($rows)
            && (count($rows) < $query->scanLimit() || $position === $window->highWater());
        if ($exhausted) {
            $position = $window->highWater();
        }

        $status = 'uncertain';
        if ($identity->canProveContinuity()) {
            $status = $exhausted && !$loss ? 'complete' : 'incomplete';
        }

        $continuation = $this->protector->seal(json_encode([
            'version'   => 1,
            'binding'   => $binding,
            'namespace' => $window->namespace(),
            'position'  => $position,
            'loss'      => $loss,
            'expires'   => time() + $this->ttl
        ], JSON_THROW_ON_ERROR));
        $result = new NoticeRecoveryBatch($notices, $status, $continuation);
        if (strlen(json_encode($result->toArray(), JSON_THROW_ON_ERROR)) > $this->maxResultBytes) {
            throw new DomainException('Notice recovery result budget exceeded.');
        }

        return $result;
    }

    /**
     * Restores only bounded versioned context-bound state without treating possession as authority
     *
     * @return array{namespace: string, position: int, loss: bool}
     */
    private function restore(string $token, string $binding): array
    {
        $plain = $this->protector->open($token);
        if (strlen($plain) > 1023) {
            throw new DomainException('Notice recovery requires restart.');
        }

        $state = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
        if (
            !is_array($state) || ($state['version'] ?? null) !== 1 || ($state['binding'] ?? null) !== $binding
            || !is_string($state['namespace'] ?? null) || preg_match('/\A[0-9a-f]{32}\z/D', $state['namespace']) !== 1
            || !is_int($state['position'] ?? null) || $state['position'] < 0
            || $state['position'] > RecordedNotice::MAX_POSITION || !is_bool($state['loss'] ?? null)
            || !is_int($state['expires'] ?? null) || $state['expires'] <= time()
            || $state['expires'] > time() + $this->ttl
        ) {
            throw new DomainException('Notice recovery requires restart.');
        }

        return [
            'namespace' => $state['namespace'],
            'position'  => $state['position'],
            'loss'      => $state['loss']
        ];
    }
}
