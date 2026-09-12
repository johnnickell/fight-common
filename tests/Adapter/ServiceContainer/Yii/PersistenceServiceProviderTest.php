<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Persistence\Yii\YiiTransactionalUnitOfWork;
use Fight\Common\Adapter\ServiceContainer\Yii\PersistenceServiceProvider;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Reference;

#[CoversClass(PersistenceServiceProvider::class)]
final class PersistenceServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_binds_the_yii_transactional_unit_of_work(): void
    {
        self::assertEquals(
            [
                TransactionalUnitOfWork::class => [
                    'class'         => YiiTransactionalUnitOfWork::class,
                    '__construct()' => [Reference::to(ConnectionInterface::class)]
                ]
            ],
            (new PersistenceServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new PersistenceServiceProvider())->getExtensions());
    }
}
