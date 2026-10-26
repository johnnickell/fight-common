<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Subscription;

use Fight\Common\Application\Mcp\Subscription\Data\SubscriberPage;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberReadScope;
use Fight\Common\Application\Mcp\Subscription\Data\SubscriberSummary;
use Fight\Common\Application\Mcp\Subscription\Exception\SubscriberReadFailed;
use Fight\Common\Application\Mcp\Subscription\QueryHandler\ListSubscribersHandler;
use Fight\Common\Application\Mcp\Subscription\QueryHandler\SummarizeSubscribersHandler;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberAccess;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReadAdmission;
use Fight\Common\Application\Mcp\Subscription\Service\SubscriberReader;
use Fight\Common\Domain\Messaging\Query\QueryMessage;
use Fight\Common\Domain\Subscription\Query\ListSubscribers;
use Fight\Common\Domain\Subscription\Query\SummarizeSubscribers;
use Fight\Common\Domain\Subscription\SubscriberSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SummarizeSubscribersHandler::class)]
#[CoversClass(ListSubscribersHandler::class)]
#[CoversClass(SubscriberReadFailed::class)]
final class SubscriberHandlersTest extends UnitTestCase
{
    public function test_that_each_routed_handler_uses_fresh_independent_admission_and_keeps_private_failures_private(): void
    {
        $selection = SubscriberSelection::create(['scope']); $scope = new SubscriberReadScope('db', 100);
        foreach ([[SummarizeSubscribersHandler::class, new SummarizeSubscribers($selection), 'summarize', SubscriberSummary::fromSubscribers([], $scope)], [ListSubscribersHandler::class, new ListSubscribers($selection), 'list', new SubscriberPage([], 0, 1, 16, $scope)]] as [$class, $query, $method, $result]) {
            $granted = SubscriberAccess::create(['scope']); $denied = SubscriberAccess::create([]);
            $admission = $this->mock(SubscriberReadAdmission::class); $reader = $this->mock(SubscriberReader::class);
            $admission->shouldReceive('currentAccess')->twice()->with($selection)->andReturn($granted, $denied);
            $reader->shouldReceive($method)->once()->with($method === 'list' ? $query : $selection, $granted)->andReturn($result);
            $reader->shouldReceive($method)->once()->with($method === 'list' ? $query : $selection, $denied)->andReturn($result);
            $handler = new $class($admission, $reader); self::assertSame($query::class, $class::queryRegistration());
            self::assertSame($result, $handler->handle(QueryMessage::create($query))); self::assertSame($result, $handler->handle(QueryMessage::create($query)));
            $failed = $this->mock(SubscriberReadAdmission::class); $cause = new RuntimeException('private credentials');
            $failed->shouldReceive('currentAccess')->once()->andThrow($cause);
            try { (new $class($failed, $reader))->handle(QueryMessage::create($query)); self::fail('No empty success'); }
            catch (SubscriberReadFailed $error) { self::assertSame('Subscriber inspection unavailable.', $error->getMessage()); self::assertSame($cause, $error->getPrevious()); }
        }
    }
}
