<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Notice;

use Doctrine\DBAL\Connection;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Adapter\Notice\Dbal\DbalNoticeReader;
use Fight\Common\Application\Notice\QueryHandler\GetNoticeHandler;
use Fight\Common\Application\Notice\QueryHandler\ListNoticesHandler;
use Fight\Common\Application\Notice\QueryHandler\SummarizeNoticesHandler;
use Fight\Common\Application\Notice\Service\NoticeAccess;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Domain\Notice\NoticeSelection;

final class NoticeInspectionFixture implements NoticeReadAdmission
{
    public NoticeAccess $access;
    public int $admissions = 0;
    public readonly RoutingQueryBus $bus;

    public function __construct(Connection $connection)
    {
        $this->access = NoticeAccess::create(['scope']);
        $reader = new DbalNoticeReader($connection); $router = new InMemoryQueryRouter();
        foreach ([SummarizeNoticesHandler::class, ListNoticesHandler::class, GetNoticeHandler::class] as $class) {
            $router->registerHandler($class::queryRegistration(), new $class($this, $reader));
        }
        $this->bus = new RoutingQueryBus($router);
    }

    public function currentAccess(NoticeSelection $selection): NoticeAccess { ++$this->admissions; return $this->access; }
}
