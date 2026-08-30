<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\ServiceProviderInterface;

/**
 * Class PersistenceServiceProvider
 *
 * Registers the Yii persistence capability.
 */
final class PersistenceServiceProvider implements ServiceProviderInterface
{
    /**
     * Returns persistence definitions without boot side effects
     *
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            TransactionalUnitOfWork::class => [
                'class'         => YiiTransactionalUnitOfWork::class,
                '__construct()' => [Reference::to(ConnectionInterface::class)]
            ]
        ];
    }

    /**
     * Returns no persistence extensions
     *
     * @return array<string, callable>
     */
    public function getExtensions(): array
    {
        return [];
    }
}
