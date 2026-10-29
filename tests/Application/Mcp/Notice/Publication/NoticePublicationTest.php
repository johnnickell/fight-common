<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mcp\Notice\Publication;

use Fight\Common\Application\Mcp\Notice\Data\NoticeView;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublication;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublicationDiagnostics;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublicationGrant;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublicationPolicy;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublicationStatus;
use Fight\Common\Application\Mcp\Notice\Publication\RecordNoticeAndPublish;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Query\QueryBus;
use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Notice\Command\RecordNotice;
use Fight\Common\Domain\Mcp\Notice\Notice;
use Fight\Common\Domain\Mcp\Notice\Query\GetNotice;
use Fight\Test\Common\TestCase\Mcp\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(NoticePublication::class)]
#[CoversClass(NoticePublicationStatus::class)]
#[CoversClass(RecordNoticeAndPublish::class)]
final class NoticePublicationTest extends UnitTestCase
{
    public function test_that_each_attempt_reconstructs_and_reauthorizes_before_selecting_exactly_one_publisher(): void
    {
        $view = $this->view(); $identity = new GetNotice('scope', 'producer', 'notice-1');
        $queries = $this->mock(QueryBus::class); $queries->shouldReceive('fetch')->with($identity)->times(4)->andReturn($view);
        $policy = $this->mock(NoticePublicationPolicy::class);
        $publicGrant = NoticePublicationGrant::create('https://feed.test/public', false);
        $privateGrant = NoticePublicationGrant::create('https://feed.test/private', true, ['subject']);
        $policy->shouldReceive('currentGrant')->with($view)->times(4)->andReturn($publicGrant, $privateGrant, null, $privateGrant);
        $public = $this->mock(Publisher::class); $public->shouldReceive('push')->once()->with($publicGrant->topic(), $publicGrant->message($view));
        $private = $this->mock(PrivatePublisher::class); $private->shouldReceive('pushPrivate')->once()->with($privateGrant->topic(), $privateGrant->message($view));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class); $diagnostics->shouldNotReceive('record');
        $operation = new NoticePublication($queries, $policy, $diagnostics, $public, $private);
        self::assertSame(NoticePublicationStatus::PUBLISHED, $operation->publish($identity));
        self::assertSame(NoticePublicationStatus::PUBLISHED, $operation->publish($identity));
        self::assertSame(NoticePublicationStatus::DENIED, $operation->publish($identity));
        self::assertSame(NoticePublicationStatus::UNSUPPORTED, (new NoticePublication($queries, $policy, $diagnostics, $public))->publish($identity));
    }

    public function test_that_absent_public_support_and_absent_or_concealed_notices_never_send(): void
    {
        $queries = $this->mock(QueryBus::class); $queries->shouldReceive('fetch')->twice()->andReturn(null, $this->view());
        $policy = $this->mock(NoticePublicationPolicy::class); $policy->shouldReceive('currentGrant')->once()->andReturn(NoticePublicationGrant::create('https://feed.test/x', false));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class); $diagnostics->shouldNotReceive('record');
        $operation = new NoticePublication($queries, $policy, $diagnostics);
        self::assertSame(NoticePublicationStatus::NOT_AVAILABLE, $operation->publish(new GetNotice('scope', 'producer', 'missing')));
        self::assertSame(NoticePublicationStatus::UNSUPPORTED, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
    }

    public function test_that_invalid_mapping_and_private_diagnostic_failures_remain_safe_and_do_not_fall_back(): void
    {
        $queries = $this->mock(QueryBus::class); $queries->shouldReceive('fetch')->times(4)->andReturn($this->view());
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldReceive('currentGrant')->once()->andThrow(new DomainException('SECRET invalid policy'));
        $policy->shouldReceive('currentGrant')->once()->andReturn(NoticePublicationGrant::create('https://feed.test/x', true, [], ['app/missing']));
        $policy->shouldReceive('currentGrant')->twice()->andReturn(NoticePublicationGrant::create('https://feed.test/x', true));
        $private = $this->mock(PrivatePublisher::class); $private->shouldReceive('pushPrivate')->twice()->andThrow(new RuntimeException('SECRET hub credentials'));
        $public = $this->mock(Publisher::class); $public->shouldNotReceive('push');
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class); $diagnostics->shouldReceive('record')->times(4)->andThrow(new RuntimeException('SECRET diagnostic sink'));
        $operation = new NoticePublication($queries, $policy, $diagnostics, $public, $private); $identity = new GetNotice('scope', 'producer', 'notice-1');
        self::assertSame('invalid', $operation->publish($identity)->value);
        self::assertSame('invalid', $operation->publish($identity)->value);
        self::assertSame('failed', $operation->publish($identity)->value);
        self::assertSame('failed', $operation->publish($identity)->value);
    }

    public function test_that_read_and_unexpected_policy_failures_are_private_and_wrong_query_composition_is_not_success(): void
    {
        $identity = new GetNotice('scope', 'producer', 'notice-1');
        $queries = $this->mock(QueryBus::class); $queries->shouldReceive('fetch')->once()->andThrow(new RuntimeException('SECRET storage'));
        $queries->shouldReceive('fetch')->once()->andReturn(['untrusted' => 'assertion']);
        $queries->shouldReceive('fetch')->once()->andReturn($this->view());
        $policy = $this->mock(NoticePublicationPolicy::class); $policy->shouldReceive('currentGrant')->once()->andThrow(new RuntimeException('SECRET policy'));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class); $diagnostics->shouldReceive('record')->times(3);
        $operation = new NoticePublication($queries, $policy, $diagnostics);
        for ($i = 0; $i < 3; ++$i) { self::assertSame(NoticePublicationStatus::FAILED, $operation->publish($identity)); }
    }

    public function test_that_composition_records_through_command_bus_before_publication_and_preserves_recording_failure(): void
    {
        $command = RecordNotice::fromArray(NoticeFixture::data()); $committed = false;
        $commands = $this->mock(CommandBus::class); $commands->shouldReceive('execute')->with($command)->once()->andReturnUsing(static function () use (&$committed): void { $committed = true; });
        $queries = $this->mock(QueryBus::class); $queries->shouldReceive('fetch')->once()->andReturnUsing(function (GetNotice $query) use (&$committed) { self::assertTrue($committed); self::assertEquals(['scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1'], $query->toArray()); return $this->view(); });
        $policy = $this->mock(NoticePublicationPolicy::class); $policy->shouldReceive('currentGrant')->once()->andReturn(null);
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class); $diagnostics->shouldNotReceive('record');
        $composition = new RecordNoticeAndPublish($commands, new NoticePublication($queries, $policy, $diagnostics));
        self::assertSame(NoticePublicationStatus::DENIED, $composition->record($command));
        $failure = new RuntimeException('Recording failed'); $commands->shouldReceive('execute')->with($command)->once()->andThrow($failure);
        try { $composition->record($command); self::fail('Recording failure must remain distinct'); } catch (RuntimeException $actual) { self::assertSame($failure, $actual); }
    }

    public function test_that_excess_reference_projection_is_invalid_before_either_publisher_is_invoked(): void
    {
        $relationships = NoticeFixture::data()['relationships'];
        $relationships['recipients'] = array_map(static fn ($i) => 'recipient-'.$i, range(1, 30));
        $view = new NoticeView(Notice::fromArray(NoticeFixture::data(['relationships' => $relationships])), '2026-10-25T02:00:00.000000Z');
        $queries = $this->mock(QueryBus::class);
        $queries->shouldReceive('fetch')->once()->andReturn($view);
        $policy = $this->mock(NoticePublicationPolicy::class);
        $policy->shouldReceive('currentGrant')->once()->with($view)->andReturn(NoticePublicationGrant::create('https://feed.test/private', true, ['recipients']));
        $diagnostics = $this->mock(NoticePublicationDiagnostics::class);
        $diagnostics->shouldReceive('record')->once()->withArgs(static fn ($failure) => $failure instanceof DomainException);
        $public = $this->mock(Publisher::class);
        $public->shouldNotReceive('push');
        $private = $this->mock(PrivatePublisher::class);
        $private->shouldNotReceive('pushPrivate');
        $operation = new NoticePublication($queries, $policy, $diagnostics, $public, $private);
        self::assertSame(NoticePublicationStatus::INVALID, $operation->publish(new GetNotice('scope', 'producer', 'notice-1')));
    }

    private function view(): NoticeView { return new NoticeView(Notice::fromArray(NoticeFixture::data()), '2026-10-25T02:00:00.000000Z'); }
}
