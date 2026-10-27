<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Error;
use ErrorException;
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
    public function test_that_getters_preserve_legacy_reads_readonly_state_and_serialization(object $value, array $expected): void
    {
        $serialized = serialize($value);
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            self::assertSame($expected, get_object_vars($value));
            foreach ($expected as $property => $state) {
                // Intentional legacy reads: these public interfaces remain supported without runtime notices.
                self::assertSame($state, $value->$property);
                self::assertSame($state, $value->$property());
                try {
                    $value->$property = $state;
                    self::fail('Legacy state must remain readonly.');
                } catch (Error $error) {
                    self::assertStringContainsString('readonly property', $error->getMessage());
                }
            }
            self::assertSame($serialized, serialize($value));
            $restored = unserialize($serialized);
            self::assertEquals($expected, get_object_vars($restored));
            foreach ($expected as $property => $state) {
                self::assertEquals($state, $restored->$property());
            }
        } finally {
            restore_error_handler();
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
