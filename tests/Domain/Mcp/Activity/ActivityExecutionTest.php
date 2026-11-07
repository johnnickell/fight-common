<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\ActivityExecution;
use Fight\Common\Domain\Mcp\Activity\ActivityResult;
use Fight\Common\Domain\Mcp\Activity\ActivityTarget;
use Fight\Common\Domain\Mcp\Activity\ActivityTiming;
use Fight\Common\Domain\Mcp\Observation\ObservationOccurrence;
use Fight\Common\Domain\Value\DateTime\Duration;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ActivityExecution::class)]
final class ActivityExecutionTest extends UnitTestCase
{
    public function test_that_execution_composes_exact_typed_facts_and_canonical_structured_json(): void
    {
        $target = ActivityTarget::fromStrings('tool', 'read');
        $timing = ActivityTiming::fromParts(ObservationOccurrence::fromString('2026-11-06T12:00:00.120Z'), null, Duration::fromMicroseconds(7));
        $result = ActivityResult::threw('IO:Unavailable');
        $execution = ActivityExecution::fromParts($target, $timing, $result);
        $expected = [
            'target' => ['capability' => 'tool', 'name' => 'read'],
            'timing' => ['started_at' => '2026-11-06T12:00:00.120Z', 'ended_at' => null, 'elapsed' => '7us'],
            'result' => ['kind' => 'threw', 'failure_kind' => 'IO:Unavailable', 'exit_code' => null],
        ];
        $text = '{"target":{"capability":"tool","name":"read"},"timing":{"started_at":"2026-11-06T12:00:00.120Z","ended_at":null,"elapsed":"7us"},"result":{"kind":"threw","failure_kind":"IO:Unavailable","exit_code":null}}';
        self::assertSame($target, $execution->target());
        self::assertSame($timing, $execution->timing());
        self::assertSame($result, $execution->result());
        self::assertSame($expected, $execution->toArray());
        self::assertSame($expected, $execution->jsonSerialize());
        self::assertSame($text, $execution->toString());
        self::assertSame($text, json_encode($execution, JSON_THROW_ON_ERROR));
        self::assertSame($text, (string) $execution);
        $reordered = array_reverse(array_map(static fn (array $fields): array => array_reverse($fields, true), $expected), true);
        foreach ([ActivityExecution::fromString($text), ActivityExecution::fromArray($reordered)] as $copy) {
            self::assertTrue($execution->equals($copy));
            self::assertSame($execution->hashValue(), $copy->hashValue());
            self::assertSame($expected, $copy->toArray());
        }
        self::assertSame([], get_object_vars($execution));
    }

    public function test_that_unknown_target_timing_and_result_are_explicit_not_guessed(): void
    {
        $execution = ActivityExecution::fromParts(null, ActivityTiming::fromParts(null, null, null), ActivityResult::unknown());
        $expected = [
            'target' => null,
            'timing' => ['started_at' => null, 'ended_at' => null, 'elapsed' => null],
            'result' => ['kind' => 'unknown', 'failure_kind' => null, 'exit_code' => null],
        ];
        self::assertNull($execution->target());
        self::assertSame($expected, $execution->toArray());
        self::assertTrue($execution->equals(ActivityExecution::fromString(json_encode($expected, JSON_THROW_ON_ERROR))));
        foreach ([
            ActivityExecution::fromParts(ActivityTarget::fromStrings('process', 'php'), $execution->timing(), $execution->result()),
            ActivityExecution::fromParts(null, ActivityTiming::fromParts(null, null, Duration::fromMicroseconds(0)), $execution->result()),
            ActivityExecution::fromParts(null, $execution->timing(), ActivityResult::returned()),
        ] as $changed) {
            self::assertFalse($execution->equals($changed));
            self::assertNotSame($execution->hashValue(), $changed->hashValue());
        }
        self::assertFalse($execution->equals($expected));
    }

    public function test_that_nested_input_and_output_aliases_cannot_mutate_execution(): void
    {
        $name = 'read';
        $elapsed = '0us';
        $kind = 'returned';
        $target = ['capability' => 'tool', 'name' => &$name];
        $timing = ['started_at' => null, 'ended_at' => null, 'elapsed' => &$elapsed];
        $result = ['kind' => &$kind, 'failure_kind' => null, 'exit_code' => null];
        $input = ['target' => &$target, 'timing' => &$timing, 'result' => &$result];
        $execution = ActivityExecution::fromArray($input);
        $name = 'write';
        $elapsed = '-1us';
        $kind = 'cancelled';
        $target = null;
        $timing = null;
        $result = null;
        $output = $execution->toArray();
        $output['target']['name'] = 'write';
        $output['timing']['elapsed'] = '999us';
        $output['result']['kind'] = 'cancelled';
        self::assertSame([
            'target' => ['capability' => 'tool', 'name' => 'read'],
            'timing' => ['started_at' => null, 'ended_at' => null, 'elapsed' => '0us'],
            'result' => ['kind' => 'returned', 'failure_kind' => null, 'exit_code' => null],
        ], $execution->toArray());
    }

    #[DataProvider('invalidStructures')]
    public function test_that_execution_requires_closed_explicit_nested_fields(array $data): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity execution structure\.\z/D');
        ActivityExecution::fromArray($data);
    }

    public static function invalidStructures(): iterable
    {
        $unknown = [
            'target' => null,
            'timing' => ['started_at' => null, 'ended_at' => null, 'elapsed' => null],
            'result' => ['kind' => 'unknown', 'failure_kind' => null, 'exit_code' => null],
        ];
        foreach (array_keys($unknown) as $field) {
            $missing = $unknown;
            unset($missing[$field]);
            yield 'missing '.$field => [$missing];
            foreach ([false, 0, 'private sentinel'] as $value) {
                yield [array_replace($unknown, [$field => $value])];
            }
        }
        yield 'null timing' => [array_replace($unknown, ['timing' => null])];
        yield 'null result' => [array_replace($unknown, ['result' => null])];
        yield 'unknown field' => [$unknown + ['arguments' => 'private sentinel']];
        yield 'list' => [[null, [], []]];
    }

    #[DataProvider('invalidNestedStructures')]
    public function test_that_execution_delegates_closed_nested_validation(array $data, string $message): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\A'.preg_quote($message, '/').'\z/D');
        ActivityExecution::fromArray($data);
    }

    public static function invalidNestedStructures(): iterable
    {
        $unknown = [
            'target' => null,
            'timing' => ['started_at' => null, 'ended_at' => null, 'elapsed' => null],
            'result' => ['kind' => 'unknown', 'failure_kind' => null, 'exit_code' => null],
        ];
        yield [array_replace($unknown, ['target' => []]), 'Invalid activity target.'];
        yield [array_replace($unknown, ['timing' => []]), 'Invalid activity timing structure.'];
        yield [array_replace($unknown, ['result' => []]), 'Invalid activity result structure.'];
        yield [array_replace($unknown, ['target' => ['capability' => 'tool', 'name' => '/private/sentinel']]), 'Invalid observation reference.'];
    }

    #[DataProvider('invalidTexts')]
    public function test_that_execution_rejects_malformed_or_scalar_text_with_fixed_error(string $text): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity execution text\.\z/D');
        ActivityExecution::fromString($text);
    }

    public static function invalidTexts(): iterable
    {
        foreach (['private sentinel', '{', 'null', 'false', '1', '"private sentinel"'] as $text) {
            yield [$text];
        }
    }
}
