<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SafeHttpFailure::class)]
#[CoversClass(SafeHttpMetadata::class)]
#[CoversClass(SafeHttpPresentation::class)]
final class SafeHttpGetterCompatibilityTest extends UnitTestCase
{
    #[DataProvider('values')]
    public function test_that_getters_preserve_configured_state_after_round_trip(object $value, array $expected): void
    {
        $restored = unserialize(serialize($value));

        foreach ($expected as $property => $state) {
            self::assertSame($state, $value->$property());
            self::assertEquals($state, $restored->$property());
        }
    }

    public static function values(): iterable
    {
        $metadata = new SafeHttpMetadata(challenge: 'Bearer realm="api"', allow: 'GET, POST', retryAfterSeconds: 0);
        yield 'metadata' => [$metadata, ['challenge' => 'Bearer realm="api"', 'allow' => 'GET, POST', 'retryAfterSeconds' => 0]];
        yield 'null metadata' => [new SafeHttpMetadata(), ['challenge' => null, 'allow' => null, 'retryAfterSeconds' => null]];
        yield 'presentation' => [new SafeHttpPresentation(metadata: $metadata, data: ['nested' => ['ok' => false, 'zero' => 0, 'none' => null]], message: ''), ['data' => ['nested' => ['ok' => false, 'zero' => 0, 'none' => null]], 'message' => '', 'metadata' => $metadata]];
        yield 'null presentation' => [new SafeHttpPresentation(), ['data' => null, 'message' => null, 'metadata' => null]];
        // Exclude PHPUnit's unpersistable provider arguments from native exception traces, not from value state.
        $previous = ini_set('zend.exception_ignore_args', '1');
        try {
            $failure = SafeHttpFailure::fromNative(status: 401, metadata: $metadata);
            $badRequest = SafeHttpFailure::badRequest();
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
        yield 'failure' => [$failure, ['status' => 401, 'metadata' => $metadata]];
        yield 'null failure metadata' => [$badRequest, ['status' => 400, 'metadata' => null]];
    }

    public function test_that_public_data_getter_cannot_mutate_the_snapshot_or_its_json_shape(): void
    {
        $presentation = new SafeHttpPresentation(data: ['nested' => ['ok' => true], 'list' => [0, null, false]]);
        $before = json_encode($presentation, JSON_THROW_ON_ERROR);
        $data = $presentation->data();
        $data['nested']['ok'] = false;
        $data['list'][] = 'private';
        self::assertSame(['nested' => ['ok' => true], 'list' => [0, null, false]], $presentation->data());
        self::assertSame($before, json_encode($presentation, JSON_THROW_ON_ERROR));
    }
}
