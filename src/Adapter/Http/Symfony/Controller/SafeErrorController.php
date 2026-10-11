<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Symfony\Controller;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Symfony\JSendResponse;
use Fight\Common\Adapter\Http\Symfony\SymfonyHttpFailure;
use Throwable;

/**
 * Class SafeErrorController
 *
 * Explicitly selected JSON error presentation; legacy ErrorController is unchanged.
 */
final readonly class SafeErrorController
{
    /**
     * Constructs SafeErrorController
     */
    public function __construct(
        private SafeHttpErrorPolicy $policy,
        private SafeHttpDiagnostics $diagnostics,
        private ?SafeHttpProjector $projector = null
    ) {
    }

    /**
     * Handles a selected JSON failure with one diagnostic attempt
     */
    public function handle(Throwable $failure): JSendResponse
    {
        try {
            $this->diagnostics->report($failure);
            $native = SymfonyHttpFailure::translate($failure);
            $status = $this->policy->status($native);
            $projection = $this->projector?->project($failure, $status);
            [$status, $envelope, $headers] = $this->policy->present($native, $projection);

            return JSendResponse::fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            // Reporting, translation, projection and serialization must all fail closed.
        }

        [$status, $envelope, $headers] = $this->policy->fallback();

        return JSendResponse::fromEnvelope($envelope, $status, $headers);
    }
}
