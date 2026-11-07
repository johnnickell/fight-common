<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Activity;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Activity\ActivityTarget;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ActivityTarget::class)]
final class ActivityTargetTest extends UnitTestCase
{
    public function test_that_target_preserves_safe_case_and_canonical_structured_equality(): void
    {
        $target = ActivityTarget::fromStrings('Tool:V1', 'Read_file-1.0');
        $expected = ['capability' => 'Tool:V1', 'name' => 'Read_file-1.0'];
        $text = '{"capability":"Tool:V1","name":"Read_file-1.0"}';
        self::assertSame('Tool:V1', $target->capability());
        self::assertSame('Read_file-1.0', $target->name());
        self::assertSame($expected, $target->toArray());
        self::assertSame($expected, $target->jsonSerialize());
        self::assertSame($text, json_encode($target, JSON_THROW_ON_ERROR));
        self::assertSame($text, $target->toString());
        self::assertSame($text, (string) $target);
        self::assertSame($text, $target->hashValue());
        foreach ([ActivityTarget::fromString($text), ActivityTarget::fromArray(array_reverse($expected, true))] as $copy) {
            self::assertTrue($target->equals($copy));
            self::assertSame($target->hashValue(), $copy->hashValue());
            self::assertSame($expected, $copy->toArray());
        }
        self::assertFalse($target->equals(ActivityTarget::fromStrings('tool:V1', 'Read_file-1.0')));
        self::assertFalse($target->equals(ActivityTarget::fromStrings('Tool:V1', 'write')));
        self::assertFalse($target->equals($expected));
        self::assertSame([], get_object_vars($target));
    }

    public function test_that_target_bounds_and_input_output_aliases_are_independent(): void
    {
        $capability = str_repeat('a', 128);
        $name = 'Z';
        $input = ['capability' => &$capability, 'name' => &$name];
        $target = ActivityTarget::fromArray($input);
        $capability = 'changed';
        $name = 'changed';
        $output = $target->toArray();
        $output['capability'] = 'changed';
        $output['name'] = 'changed';
        self::assertSame(['capability' => str_repeat('a', 128), 'name' => 'Z'], $target->toArray());
        self::assertSame(str_repeat('z', 128), ActivityTarget::fromStrings('a', str_repeat('z', 128))->name());
    }

    #[DataProvider('invalidReferences')]
    public function test_that_target_rejects_unsafe_or_out_of_bounds_references(string $reference): void
    {
        foreach ([[$reference, 'read'], ['tool', $reference]] as [$capability, $name]) {
            try {
                ActivityTarget::fromStrings($capability, $name);
                self::fail('Expected unsafe reference rejection.');
            } catch (DomainException $exception) {
                self::assertSame('Invalid observation reference.', $exception->getMessage());
            }
        }
    }

    public static function invalidReferences(): iterable
    {
        foreach (['', str_repeat('a', 129), ' read', 'read ', '/private/secret', 'https://private.invalid', 'read file', 'read;secret', "read\nsecret", '測', "\xff", '.hidden', '-option'] as $reference) {
            yield [$reference];
        }
    }

    #[DataProvider('invalidStructures')]
    public function test_that_target_rejects_noncanonical_structure_with_fixed_error(array $data): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity target\.\z/D');
        ActivityTarget::fromArray($data);
    }

    public static function invalidStructures(): iterable
    {
        foreach ([[], ['capability' => 'tool'], ['name' => 'read'], ['tool', 'read'], ['capability' => null, 'name' => 'read'], ['capability' => 'tool', 'name' => 1], ['capability' => 'tool', 'name' => 'read', 'arguments' => 'private sentinel']] as $data) {
            yield [$data];
        }
    }

    #[DataProvider('invalidTexts')]
    public function test_that_target_rejects_malformed_or_scalar_text_with_fixed_error(string $text): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/\AInvalid activity target text\.\z/D');
        ActivityTarget::fromString($text);
    }

    public static function invalidTexts(): iterable
    {
        foreach (['private sentinel', '{', 'null', 'true', '1', '"private sentinel"'] as $text) {
            yield [$text];
        }
    }
}
