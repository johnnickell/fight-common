<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mcp\Notice;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Mcp\Notice\QueryHandler\GetNoticeHandler;
use Fight\Common\Application\Mcp\Notice\QueryHandler\ListNoticesHandler;
use Fight\Common\Application\Mcp\Notice\QueryHandler\SummarizeNoticesHandler;
use Fight\Common\Application\Mcp\Notice\Service\NoticeAccess;
use Fight\Common\Application\Mcp\Notice\Service\NoticeReadAdmission;
use Fight\Common\Domain\Mcp\Notice\NoticeSelection;

final class NoticeInspectionFixture implements NoticeReadAdmission
{
    public NoticeAccess $access;
    public int $admissions = 0;
    public readonly RoutingQueryBus $queryBus;

    public function __construct(Connection $connection, DbalNoticeTables $tables = new DbalNoticeTables())
    {
        $this->access = NoticeAccess::create(['scope']);
        $reader = new DbalNoticeReader($connection, $tables); $router = new InMemoryQueryRouter();
        foreach ([SummarizeNoticesHandler::class, ListNoticesHandler::class, GetNoticeHandler::class] as $class) {
            $router->registerHandler($class::queryRegistration(), new $class($this, $reader));
        }
        $this->queryBus = new RoutingQueryBus($router);
    }

    public function currentAccess(NoticeSelection $selection): NoticeAccess { ++$this->admissions; return $this->access; }
}
