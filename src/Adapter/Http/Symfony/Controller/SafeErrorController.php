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
    private SafeHttpErrorPolicy $httpErrorPolicy;
    private ?SafeHttpProjector $httpErrorProjector;

    /**
     * Constructs SafeErrorController
     */
    public function __construct(
        SafeHttpErrorPolicy $policy,
        private SafeHttpDiagnostics $diagnostics,
        ?SafeHttpProjector $projector = null
    ) {
        $this->httpErrorPolicy = $policy;
        $this->httpErrorProjector = $projector;
    }

    /**
     * Handles a selected JSON failure with one diagnostic attempt
     */
    public function handle(Throwable $failure): JSendResponse
    {
        try {
            $this->diagnostics->report($failure);
            $native = SymfonyHttpFailure::translate($failure);
            $status = $this->httpErrorPolicy->status($native);
            $projection = $this->httpErrorProjector?->project($failure, $status);
            [$status, $envelope, $headers] = $this->httpErrorPolicy->present($native, $projection);

            return JSendResponse::fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            // Reporting, translation, projection and serialization must all fail closed.
        }

        try {
            [$status, $envelope, $headers] = $this->httpErrorPolicy->fallback();

            return JSendResponse::fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            // A failed encoder cannot be trusted to render even the generic envelope.
        }

        return new JSendResponse(
            '{"status":"error","message":"Internal server error."}',
            500,
            ['Content-Type' => 'application/json'],
            true
        );
    }
}
