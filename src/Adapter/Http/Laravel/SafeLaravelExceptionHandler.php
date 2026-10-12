<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Laravel;

use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WeakMap;

/**
 * Class SafeLaravelExceptionHandler
 *
 * Explicit opt-in replacement for the Laravel exception handler contract.
 * Owns reporting before the framework's raw logger/context callbacks can run.
 */
final class SafeLaravelExceptionHandler extends Handler
{
    /**
     * @var WeakMap<Throwable, bool>
     */
    private WeakMap $reported;

    /**
     * Constructs SafeLaravelExceptionHandler
     */
    public function __construct(
        Container $container,
        private readonly SafeHttpErrorPolicy $policy,
        private readonly SafeHttpDiagnostics $diagnostics,
        private readonly ?SafeHttpProjector $projector = null
    ) {
        parent::__construct($container);
        $this->reported = new WeakMap();
    }

    /**
     * Reports once through the sole trusted sink, even for native ignored failures
     */
    public function report(Throwable $e): void
    {
        if (isset($this->reported[$e])) {
            return;
        }

        // A failed reporter is not a successful diagnostic; never recurse or invoke
        // Laravel's unredacted report callbacks, exception methods or log formatter.
        $this->reported[$e] = false;
        try {
            $this->diagnostics->report($e);
            $this->reported[$e] = true;
        } catch (Throwable) {
            // Render returns a generic 500 when this attempt did not succeed.
        }
    }

    /**
     * Returns true because this composition reports native ignored failures too
     */
    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    /**
     * Renders selected JSON safely and delegates non-JSON to Laravel
     */
    public function render($request, Throwable $e): Response
    {
        if (!$request->expectsJson()) {
            return parent::render($request, $e);
        }

        // A direct render call and the Kernel/Pipeline report-then-render journey
        // share one diagnostic attempt for this Throwable instance.
        $this->report($e);
        if (!$this->reported[$e]) {
            return $this->fallback();
        }

        try {
            $native = LaravelHttpFailure::translate($e);
            $projection = $this->projector?->project($e, $this->policy->status($native));
            [$status, $envelope, $headers] = $this->policy->present($native, $projection);

            return JSendResponse::fromEnvelope($envelope, $status, $headers);
        } catch (Throwable) {
            return $this->fallback();
        }
    }

    /**
     * Returns a fixed response without consulting a failed projector or reporter
     */
    private function fallback(): JSendResponse
    {
        [$status, $envelope, $headers] = $this->policy->fallback();

        return JSendResponse::fromEnvelope($envelope, $status, $headers);
    }
}
