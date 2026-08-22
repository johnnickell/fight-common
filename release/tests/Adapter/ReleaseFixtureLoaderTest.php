<?php

declare(strict_types=1);

namespace Fight\Test\Release\Adapter;

use Fight\Release\Adapter\ReleaseFixtureLoader;
use Fight\Release\Adapter\ReleaseFixtureSnapshot;
use Fight\Release\Adapter\ReleaseJsonObjectMemberValidator;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ReleaseFixtureLoaderTest
 *
 * Covers immutable bootstrap fixture loading.
 */
#[CoversClass(ReleaseFixtureLoader::class)]
#[CoversClass(ReleaseFixtureSnapshot::class)]
#[CoversClass(ReleaseJsonObjectMemberValidator::class)]
class ReleaseFixtureLoaderTest extends UnitTestCase
{
    /**
     * Covers one immutable parse surviving a deterministic fixture swap.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_load_captures_one_candidate_snapshot_before_the_fixture_is_replaced(): void
    {
        $path = sys_get_temp_dir().'/release-fixture-snapshot-'.bin2hex(random_bytes(8)).'.json';
        file_put_contents($path, '{"boundary":{"effect_class":"git.inspect_repository","outcome":"success"}}');

        try {
            $snapshot = new ReleaseFixtureLoader()->load($path);
            file_put_contents($path, '{"boundary":{"effect_class":"github.release","outcome":"success"}}');

            self::assertSame('valid', $snapshot->status);
            self::assertSame('git.inspect_repository', $snapshot->candidate['boundary']['effect_class'] ?? null);
        } finally {
            unlink($path);
        }
    }

    /**
     * Covers every bootstrap parse stop without a release-ledger effect.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function test_that_load_classifies_unreadable_encoding_invalid_and_non_object_fixtures(): void
    {
        $path = sys_get_temp_dir().'/release-fixture-invalid-'.bin2hex(random_bytes(8)).'.json';
        $loader = new ReleaseFixtureLoader();

        self::assertSame('unreadable', $loader->load($path)->status);
        self::assertSame('invalid', $loader->load("\0")->status);

        try {
            file_put_contents($path, "{\"invalid\":\"\xFF\"}");
            self::assertSame('encoding_invalid', $loader->load($path)->status);

            foreach (['{', '[]', '[{}]', '"string"', '42', 'null'] as $fixture) {
                file_put_contents($path, $fixture);
                self::assertSame('invalid', $loader->load($path)->status, $fixture);
            }

            file_put_contents($path, '{}');
            $emptyObject = $loader->load($path);
            self::assertSame('valid', $emptyObject->status);
            self::assertSame([], $emptyObject->candidate);

            file_put_contents($path, '{"nested":{"items":[{"name":"one"}]}}');
            self::assertSame(
                ['nested' => ['items' => [['name' => 'one']]]],
                $loader->load($path)->candidate
            );

            file_put_contents(
                $path,
                " \t\r\n{\"numbers\":[-1,0,1.25,2e3],\"truth\":true,\"lie\":false,\"nothing\":null,\"empty\":[]} "
            );
            self::assertSame('valid', $loader->load($path)->status);

            foreach (
                [
                    '',
                    '{"missing-colon" 1}',
                    '{"a":1 "b":2}',
                    '[1 2]',
                    "{\"control\":\"line\nbreak\"}",
                    '{"unicode":"\\uZZZZ"}',
                    '{"escape":"\\x"}',
                    '{"unterminated":"value}',
                    '{"literal":tru}',
                    '{"number":+1}',
                    '{} trailing'
                ] as $fixture
            ) {
                file_put_contents($path, $fixture);
                self::assertSame('invalid', $loader->load($path)->status, $fixture);
            }

            file_put_contents($path, str_repeat(' ', 1_048_577));
            self::assertSame('invalid', $loader->load($path)->status);

            file_put_contents($path, str_repeat('[', 66).str_repeat(']', 66));
            self::assertSame('invalid', $loader->load($path)->status);
        } finally {
            unlink($path);
        }
    }

    /**
     * Covers duplicate object members before JSON decoding can apply last-member-wins semantics.
     *
     * @phpcsSuppress PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function test_that_load_rejects_duplicate_decoded_member_names_at_every_object_depth(): void // phpcs:ignore
    {
        $path = sys_get_temp_dir().'/release-fixture-duplicate-'.bin2hex(random_bytes(8)).'.json';
        $loader = new ReleaseFixtureLoader();
        $duplicates = [
            '{"approved_version":"1.2.3","approved_version":"1.2.4"}',
            '{"baseline":{"version":"1.2.3","version":"1.2.4"}}',
            '{"baseline":{"version":"1.2.3","ver\\u0073ion":"1.2.4"}}',
            '{"boundary":{"effect_class":"git.read","outcome":"success","outcome":"failure"}}',
            '{"nested":{"quote\\\"key":1,"quote\\u0022key":2}}',
            '{"nested":{"slash\\\\key":1,"slash\\u005ckey":2}}',
            '{"nested":{"face\\ud83d\\ude00":1,"face😀":2}}'
        ];

        try {
            foreach ($duplicates as $fixture) {
                file_put_contents($path, $fixture);
                self::assertSame('invalid', $loader->load($path)->status, $fixture);
            }

            file_put_contents(
                $path,
                '{"items":[{"version":"1.2.3"},{"version":"1.2.4"}],"values":["same","same"]}'
            );
            self::assertSame('valid', $loader->load($path)->status);
        } finally {
            unlink($path);
        }
    }
}
