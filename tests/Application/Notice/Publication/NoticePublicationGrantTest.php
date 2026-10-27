<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Notice\Publication;

use Fight\Common\Application\Notice\Data\NoticeView;
use Fight\Common\Application\Notice\Publication\NoticePublicationGrant;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Notice;
use Fight\Test\Common\TestCase\Notice\NoticeFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NoticePublicationGrant::class)]
final class NoticePublicationGrantTest extends UnitTestCase
{
    public function test_that_projection_retains_only_selected_saved_fields_and_distinct_unknown_relationships(): void
    {
        $view = $this->view(['metadata' => ['app/label' => '雪"\\/', 'app/hidden' => 'CONCEALED']]);
        $grant = NoticePublicationGrant::create('https://feed.test/notices', true, NoticePublicationGrant::FIELDS, ['app/label']);
        self::assertSame('https://feed.test/notices', $grant->topic());
        self::assertTrue($grant->isPrivate());
        $data = json_decode($grant->message($view), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($view->notice()->relationships()->toArray(), $data['relationships']);
        self::assertSame('subject-agent', $data['relationships']['subject']);
        self::assertSame('author-agent', $data['relationships']['author']);
        self::assertSame('command-id', $data['relationships']['command']);
        self::assertSame(['app/label' => '雪"\\/'], $data['metadata']);
        self::assertStringNotContainsString('CONCEALED', $grant->message($view));
        self::assertSame('2026-10-25T01:02:03.12Z', $data['occurred_at']);
        self::assertSame('2026-10-25T02:00:00.000000Z', $data['recorded_at']);
        self::assertFalse(NoticePublicationGrant::create('https://feed.test/notices', false)->isPrivate());
        self::assertSame(['version' => 1, 'scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1'], json_decode(NoticePublicationGrant::create('https://feed.test/notices', false)->message($view), true));
        foreach ([null, []] as $recipients) {
            $unknown = array_fill_keys(['subject', 'author', 'command', 'event', 'action'], null) + ['recipients' => $recipients];
            $projection = json_decode($grant->message($this->view(['relationships' => $unknown, 'occurred_at' => null])), true);
            self::assertSame($recipients, $projection['relationships']['recipients']);
            self::assertNull($projection['relationships']['subject']);
            self::assertNull($projection['occurred_at']);
        }
    }

    #[DataProvider('invalidGrants')]
    public function test_that_invalid_topics_fields_and_metadata_reject_before_mapping(string $topic, array $fields, array $metadata): void
    {
        $this->expectException(DomainException::class);
        NoticePublicationGrant::create($topic, true, $fields, $metadata);
    }

    public static function invalidGrants(): iterable
    {
        foreach (['', '/relative', 'http://feed.test/x', 'https://user:secret@feed.test/x', 'https://feed.test/x?token=secret', "https://feed.test/x\n", 'https://feed.test/雪', 'https://feed.test:65536/x', 'https://feed..test/x', 'https://-feed.test/x', 'https://feed.test/'.str_repeat('x', 239)] as $topic) { yield [$topic, [], []]; }
        foreach ([['body'], [1], [[]], ['subject', 'subject'], array_fill(0, 11, 'topic'), ['key' => 'topic']] as $fields) { yield ['https://feed.test/x', $fields, []]; }
        foreach ([['bad'], [1], [[]], ['app/key', 'app/key'], array_fill(0, 17, 'app/key'), ['key' => 'app/key']] as $metadata) { yield ['https://feed.test/x', [], $metadata]; }
    }

    public function test_that_exact_transport_topic_and_reference_limits_do_not_truncate(): void
    {
        $topic = 'https://feed.test/'.str_repeat('x', 238);
        self::assertSame(256, strlen($topic));
        self::assertSame($topic, NoticePublicationGrant::create($topic, true)->topic());
        $relations = NoticeFixture::data()['relationships'];
        $relations['recipients'] = array_map(static fn ($i) => 'recipient-'.$i, range(1, 29));
        $grant = NoticePublicationGrant::create('https://feed.test/x', true, ['recipients']);
        self::assertCount(29, json_decode($grant->message($this->view(['relationships' => $relations])), true)['relationships']['recipients']);
        $relations['recipients'][] = 'recipient-30';
        $this->expectException(DomainException::class);
        $grant->message($this->view(['relationships' => $relations]));
    }

    public function test_that_actual_json_byte_limit_includes_escaped_multibyte_values_and_rejects_one_byte_over(): void
    {
        $relations = NoticeFixture::data()['relationships'];
        $relations['recipients'] = array_map(static fn ($i) => str_pad('r'.$i, 96, 'x'), range(1, 29));
        sort($relations['recipients'], SORT_STRING);
        $metadata = ['app/unicode' => str_repeat('雪"\\/', 15)];
        for ($i = 0; $i < 8; ++$i) { $metadata['app/label'.$i] = str_repeat('a', 90); }
        $grant = NoticePublicationGrant::create('https://feed.test/x', true, ['recipients'], array_keys($metadata));
        // Derive padding from the independent wire shape, not from a rounded character count.
        $expected = ['version' => 1, 'scope' => 'scope', 'producer' => 'producer', 'id' => 'notice-1', 'relationships' => ['recipients' => $relations['recipients']], 'metadata' => $metadata];
        $base = strlen(json_encode($expected, JSON_THROW_ON_ERROR));
        $padding = 4096 - $base;
        self::assertGreaterThanOrEqual(0, $padding); self::assertLessThanOrEqual(128, $padding);
        for ($i = 0; $padding > 0; ++$i) { $extra = min(38, $padding); $metadata['app/label'.$i] .= str_repeat('a', $extra); $padding -= $extra; }
        $expected['metadata'] = $metadata;
        self::assertSame(4096, strlen(json_encode($expected, JSON_THROW_ON_ERROR)));
        self::assertSame(json_encode($expected, JSON_THROW_ON_ERROR), $grant->message($this->view(['relationships' => $relations, 'metadata' => $metadata])));
        $metadata['app/label7'] .= 'a';
        $this->expectException(DomainException::class);
        $grant->message($this->view(['relationships' => $relations, 'metadata' => $metadata]));
    }

    public function test_that_missing_disclosed_metadata_is_not_invented_or_silently_omitted(): void
    {
        $this->expectException(DomainException::class);
        NoticePublicationGrant::create('https://feed.test/x', true, [], ['app/missing'])->message($this->view());
    }

    public function test_that_validated_grants_detach_caller_owned_field_and_metadata_element_references(): void
    {
        $field = 'subject';
        $key = 'app/label';
        $grant = NoticePublicationGrant::create('https://feed.test/private', true, [&$field], [&$key]);
        $field = 'author';
        $key = 'app/hidden';
        $message = json_decode($grant->message($this->view(['metadata' => ['app/label' => 'selected', 'app/hidden' => 'CONCEALED'] ])), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['subject' => 'subject-agent'], $message['relationships']);
        self::assertSame(['app/label' => 'selected'], $message['metadata']);
    }

    public function test_that_all_sixteen_metadata_fields_and_exact_dns_label_bound_are_supported(): void
    {
        $metadata = [];
        for ($i = 0; $i < 16; ++$i) {
            $metadata['app/key'.$i] = $i;
        }
        $topic = 'https://'.str_repeat('a', 63).'.test/x';
        $grant = NoticePublicationGrant::create($topic, true, [], array_keys($metadata));
        self::assertSame($topic, $grant->topic());
        self::assertCount(16, json_decode($grant->message($this->view(['metadata' => $metadata])), true)['metadata']);
        $this->expectException(DomainException::class);
        NoticePublicationGrant::create('https://'.str_repeat('a', 64).'.test/x', true);
    }

    private function view(array $changes = []): NoticeView { return new NoticeView(Notice::fromArray(NoticeFixture::data($changes)), '2026-10-25T02:00:00.000000Z'); }
}
