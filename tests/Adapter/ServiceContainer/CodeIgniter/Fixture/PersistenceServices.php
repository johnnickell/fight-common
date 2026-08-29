<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Database\BaseConnection;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\PersistenceServices;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use RuntimeException;

/**
 * Project-owned persistence-only Config\Services fixture.
 */
final class Services extends BaseService
{
    public static function fightCodeIgniterTransactionalUnitOfWork(
        bool $getShared = true
    ): CodeIgniterTransactionalUnitOfWork {
        if ($getShared) {
            return static::getSharedInstance('fightCodeIgniterTransactionalUnitOfWork');
        }

        return PersistenceServices::codeIgniterTransactionalUnitOfWork(static::fightDatabaseConnection());
    }

    public static function fightTransactionalUnitOfWork(bool $getShared = true): TransactionalUnitOfWork
    {
        return static::fightCodeIgniterTransactionalUnitOfWork($getShared);
    }

    private static function fightDatabaseConnection(): BaseConnection
    {
        $connection = static::get('fightDatabaseConnectionCollaborator');

        if (! $connection instanceof BaseConnection) {
            throw new RuntimeException('The project database connection collaborator is unavailable.');
        }

        return $connection;
    }
}
