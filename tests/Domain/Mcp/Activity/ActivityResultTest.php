<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\ActivityResult;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ActivityResult::class)]
final class ActivityResultTest extends UnitTestCase
{
    #[DataProvider('observedResults')]
    public function test_that_result_preserves_only_observed_kind_specific_facts(ActivityResult $result, array $expected): void
    {
        self::assertSame($expected['kind'], $result->kind());
        self::assertSame($expected['failure_kind'], $result->failureKind());
        self::assertSame($expected['exit_code'], $result->exitCode());
        self::assertSame($expected, $result->toArray());
        self::assertSame($expected, $result->jsonSerialize());
        $text = json_encode($expected, JSON_THROW_ON_ERROR);
        self::assertSame($text, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertSame($text, $result->toString());
        self::assertSame($text, (string) $result);
        foreach ([ActivityResult::fromString($text), ActivityResult::fromArray(array_reverse($expected, true))] as $copy) {
            self::assertTrue($result->equals($copy));
            self::assertSame($result->hashValue(), $copy->hashValue());
            self::assertSame($expected, $copy->toArray());
        }
        self::assertSame([], get_object_vars($result));
    }

    public static function observedResults(): iterable
    {
        yield 'unknown' => [ActivityResult::unknown(), ['kind' => 'unknown', 'failure_kind' => null, 'exit_code' => null]];
        yield 'returned' => [ActivityResult::returned(), ['kind' => 'returned', 'failure_kind' => null, 'exit_code' => null]];
        yield 'threw' => [ActivityResult::threw('IO:Unavailable-1.0'), ['kind' => 'threw', 'failure_kind' => 'IO:Unavailable-1.0', 'exit_code' => null]];
        yield 'cancelled' => [ActivityResult::cancelled(), ['kind' => 'cancelled', 'failure_kind' => null, 'exit_code' => null]];
        foreach ([PHP_INT_MIN, -1, 0, 1, 255, PHP_INT_MAX] as $exitCode) {
            yield 'native exit '.$exitCode => [ActivityResult::exited($exitCode), ['kind' => 'exited', 'failure_kind' => null, 'exit_code' => $exitCode]];
        }
    }

    public function test_that_each_observed_kind_and_payload_participates_in_equality(): void
    {
        $results = [ActivityResult::unknown(), ActivityResult::returned(), ActivityResult::cancelled(), ActivityResult::exited(0), ActivityResult::exited(1), ActivityResult::threw('IO'), ActivityResult::threw('io')];
        foreach ($results as $leftIndex => $left) {
            foreach ($results as $rightIndex => $right) {
                self::assertSame($leftIndex === $rightIndex, $left->equals($right));
                self::assertSame($leftIndex === $rightIndex, $left->hashValue() === $right->hashValue());
            }
        }
        self::assertFalse(ActivityResult::unknown()->equals(null));
    }

    public function test_that_result_does_not_retain_input_or_expose_output_aliases(): void
    {
        $kind = 'exited';
        $exitCode = PHP_INT_MIN;
        $input = ['kind' => &$kind, 'failure_kind' => null, 'exit_code' => &$exitCode];
        $result = ActivityResult::fromArray($input);
        $kind = 'returned';
        $exitCode = 0;
        $output = $result->toArray();
        $output['kind'] = 'returned';
        $output['exit_code'] = null;
        self::assertSame(['kind' => 'exited', 'failure_kind' => null, 'exit_code' => PHP_INT_MIN], $result->toArray());
        self::assertSame(str_repeat('a', 128), ActivityResult::threw(str_repeat('a', 128))->failureKind());
        self::assertSame('A', ActivityResult::threw('A')->failureKind());
    }

    #[DataProvider('invalidFailureKinds')]
    public function test_that_thrown_failure_is_a_safe_reference_not_exception_text(string $failureKind): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid observation reference\.\z/D');
        ActivityResult::threw($failureKind);
    }

    public static function invalidFailureKinds(): iterable
    {
        foreach (['', str_repeat('a', 129), 'private exception message', '/private/file', 'https://private.invalid', "IO\nprivate", '測', "\xff"] as $failureKind) {
            yield [$failureKind];
        }
    }

    #[DataProvider('invalidResults')]
    public function test_that_result_rejects_incompatible_or_unsupported_closed_forms(array $data, string $message): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\A'.preg_quote($message, '/').'\z/D');
        ActivityResult::fromArray($data);
    }

    public static function invalidResults(): iterable
    {
        $unknown = ['kind' => 'unknown', 'failure_kind' => null, 'exit_code' => null];
        foreach (array_keys($unknown) as $field) {
            $missing = $unknown;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing, 'Invalid activity result structure.'];
        }
        foreach ([[], ['unknown', null, null], $unknown + ['private' => 'sentinel'], array_replace($unknown, ['kind' => 1])] as $data) {
            yield [$data, 'Invalid activity result structure.'];
        }
        foreach (['unknown', 'returned', 'cancelled', 'unsupported'] as $kind) {
            foreach ([['failure_kind' => 'private'], ['exit_code' => 0], ['failure_kind' => [], 'exit_code' => false]] as $fields) {
                yield [array_replace($unknown, ['kind' => $kind], $fields), 'Invalid activity result fields.'];
            }
        }
        foreach (['unsupported', 'success', 'RETURNED', 'threw', 'exited', ''] as $kind) {
            yield [array_replace($unknown, ['kind' => $kind]), 'Invalid activity result kind.'];
        }
        foreach (['0', 0.0, true, [], ((string) PHP_INT_MAX).'0'] as $exitCode) {
            yield [['kind' => 'exited', 'failure_kind' => null, 'exit_code' => $exitCode], 'Invalid activity result fields.'];
        }
        yield [['kind' => 'threw', 'failure_kind' => 'IO', 'exit_code' => 1], 'Invalid activity result fields.'];
        yield [['kind' => 'threw', 'failure_kind' => 1, 'exit_code' => null], 'Invalid activity result fields.'];
        yield [['kind' => 'exited', 'failure_kind' => 'private', 'exit_code' => 1], 'Invalid activity result fields.'];
        yield [['kind' => 'threw', 'failure_kind' => 'private exception text', 'exit_code' => null], 'Invalid observation reference.'];
    }

    #[DataProvider('invalidTexts')]
    public function test_that_result_rejects_malformed_or_scalar_text_with_fixed_error(string $text): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity result text\.\z/D');
        ActivityResult::fromString($text);
    }

    public static function invalidTexts(): iterable
    {
        foreach (['private sentinel', '{', 'null', 'false', '1', '"private sentinel"'] as $text) {
            yield [$text];
        }
    }
}
