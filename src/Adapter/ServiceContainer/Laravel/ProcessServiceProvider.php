<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Process\Symfony\SymfonyProcessRunner;
use Fight\Common\Application\Process\ProcessRunner;
use Illuminate\Support\ServiceProvider;

/**
 * Class ProcessServiceProvider
 *
 * Registers the complete Symfony Process fallback.
 *
 * Laravel's one-shot process API does not model Fight's attached-process
 * lifecycle, clear operation, or retry behavior.
 */
final class ProcessServiceProvider extends ServiceProvider
{
    /**
     * Registers the process capability
     */
    public function register(): void
    {
        $this->app->singleton(
            ProcessRunner::class,
            static fn (): SymfonyProcessRunner => new SymfonyProcessRunner()
        );
    }
}
