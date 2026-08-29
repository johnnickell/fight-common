<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Laravel;

use Fight\Common\Adapter\Persistence\Laravel\LaravelTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

/**
 * Class PersistenceServiceProvider
 */
final class PersistenceServiceProvider extends ServiceProvider
{
    /**
     * Registers the transactional unit of work
     */
    public function register(): void
    {
        $this->app->singleton(
            TransactionalUnitOfWork::class,
            static function (Container $container): LaravelTransactionalUnitOfWork {
                $connection = $container->make('db.connection');
                assert($connection instanceof Connection);

                return new LaravelTransactionalUnitOfWork($connection);
            }
        );
    }
}
