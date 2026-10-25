<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Notice\QueryHandler;

use Fight\Common\Application\Notice\Data\NoticePage;
use Fight\Common\Application\Notice\Data\NoticeSummary;
use Fight\Common\Application\Notice\Exception\NoticeReadFailed;
use Fight\Common\Application\Notice\QueryHandler\GetNoticeHandler;
use Fight\Common\Application\Notice\QueryHandler\ListNoticesHandler;
use Fight\Common\Application\Notice\QueryHandler\SummarizeNoticesHandler;
use Fight\Common\Application\Notice\Service\NoticeAccess;
use Fight\Common\Application\Notice\Service\NoticeReadAdmission;
use Fight\Common\Application\Notice\Service\NoticeReader;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Notice\NoticeSelection;
use Fight\Common\Domain\Notice\Query\GetNotice;
use Fight\Common\Domain\Notice\Query\ListNotices;
use Fight\Common\Domain\Notice\Query\SummarizeNotices;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(GetNoticeHandler::class)]
#[CoversClass(ListNoticesHandler::class)]
#[CoversClass(SummarizeNoticesHandler::class)]
final class NoticeQueryHandlersTest extends UnitTestCase
{
    public function test_that_handlers_use_fresh_trusted_admission_and_preserve_typed_results(): void
    {
        $selection = NoticeSelection::create(['scope']);
        foreach ([
            [SummarizeNoticesHandler::class, new SummarizeNotices($selection), 'summarize', NoticeSummary::fromNotices([])],
            [ListNoticesHandler::class, new ListNotices($selection), 'list', new NoticePage([], 0, 1, 32)],
            [GetNoticeHandler::class, new GetNotice('scope', 'producer', 'id'), 'inspect', null]
        ] as [$class, $query, $method, $result]) {
            $access = NoticeAccess::create([]); $admission = $this->mock(NoticeReadAdmission::class); $reader = $this->mock(NoticeReader::class);
            $admission->shouldReceive('currentAccess')->twice()->with($query->selection())->andReturn($access);
            $reader->shouldReceive($method)->twice()->with($method === 'summarize' ? $query->selection() : $query, $access)->andReturn($result);
            $handler = new $class($admission, $reader); self::assertSame($query::class, $class::queryRegistration());
            self::assertSame($result, $handler->handle(QueryMessage::create($query)));
            self::assertSame($result, $handler->handle(QueryMessage::create($query)));
            $cause = new RuntimeException('private policy failure');
            $denied = $this->mock(NoticeReadAdmission::class); $denied->shouldReceive('currentAccess')->once()->andThrow($cause);
            try { (new $class($denied, $reader))->handle(QueryMessage::create($query)); self::fail('Policy failure must not leak.'); }
            catch (NoticeReadFailed $failure) { self::assertSame($cause, $failure->getPrevious()); self::assertSame('Retained notices could not be read completely.', $failure->getMessage()); }
        }
    }
}
