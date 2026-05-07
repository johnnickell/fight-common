<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\ImmutableException;
use Fight\Common\Domain\Exception\IndexException;
use Fight\Common\Domain\Value\Basic\MbStringObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MbStringObject::class)]
class MbStringObjectTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function test_that_from_string_creates_instance_with_correct_value(): void
    {
        $str = MbStringObject::fromString('hello');

        self::assertSame('hello', $str->value());
    }

    public function test_that_create_creates_instance_with_correct_value(): void
    {
        $str = MbStringObject::create('hello');

        self::assertSame('hello', $str->value());
    }

    public function test_that_named_constructors_accept_empty_string(): void
    {
        self::assertSame('', MbStringObject::fromString('')->value());
        self::assertSame('', MbStringObject::create('')->value());
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function test_that_value_returns_raw_string(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->value());
    }

    public function test_that_length_returns_character_count(): void
    {
        self::assertSame(5, MbStringObject::create('hello')->length());
    }

    public function test_that_length_returns_zero_for_empty_string(): void
    {
        self::assertSame(0, MbStringObject::create('')->length());
    }

    public function test_that_length_counts_multibyte_characters_not_bytes(): void
    {
        // "日本語" is 3 characters but 9 bytes in UTF-8
        self::assertSame(3, MbStringObject::create('日本語')->length());
    }

    public function test_that_is_empty_returns_true_for_empty_string(): void
    {
        self::assertTrue(MbStringObject::create('')->isEmpty());
    }

    public function test_that_is_empty_returns_false_for_non_empty_string(): void
    {
        self::assertFalse(MbStringObject::create('x')->isEmpty());
    }

    public function test_that_count_returns_character_count(): void
    {
        self::assertSame(4, MbStringObject::create('abcd')->count());
        self::assertSame(4, count(MbStringObject::create('abcd')));
    }

    // -------------------------------------------------------------------------
    // String conversion
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_raw_value(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->toString());
    }

    public function test_that_magic_to_string_returns_raw_value(): void
    {
        self::assertSame('hello', (string) MbStringObject::create('hello'));
    }

    public function test_that_json_serialize_returns_raw_value(): void
    {
        $str = MbStringObject::create('hello');

        self::assertSame('"hello"', json_encode($str));
    }

    public function test_that_hash_value_returns_raw_value(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->hashValue());
    }

    // -------------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------------

    public function test_that_equals_returns_true_for_same_instance(): void
    {
        $str = MbStringObject::create('hello');

        self::assertTrue($str->equals($str));
    }

    public function test_that_equals_returns_true_for_equal_values(): void
    {
        self::assertTrue(
            MbStringObject::create('hello')->equals(MbStringObject::create('hello'))
        );
    }

    public function test_that_equals_returns_false_for_different_values(): void
    {
        self::assertFalse(
            MbStringObject::create('hello')->equals(MbStringObject::create('world'))
        );
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        self::assertFalse(
            MbStringObject::create('hello')->equals(new \stdClass())
        );
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_same_instance(): void
    {
        $str = MbStringObject::create('hello');

        self::assertSame(0, $str->compareTo($str));
    }

    public function test_that_compare_to_returns_zero_for_equal_values(): void
    {
        self::assertSame(
            0,
            MbStringObject::create('hello')->compareTo(MbStringObject::create('hello'))
        );
    }

    public function test_that_compare_to_returns_negative_for_lesser_value(): void
    {
        self::assertSame(
            -1,
            MbStringObject::create('abc')->compareTo(MbStringObject::create('abd'))
        );
    }

    public function test_that_compare_to_returns_positive_for_greater_value(): void
    {
        self::assertSame(
            1,
            MbStringObject::create('abd')->compareTo(MbStringObject::create('abc'))
        );
    }

    // -------------------------------------------------------------------------
    // Character access: get()
    // -------------------------------------------------------------------------

    public static function provideGetValidIndices(): array
    {
        return [
            'first character'        => [0, 'h'],
            'last character'         => [4, 'o'],
            'middle character'       => [2, 'l'],
            'negative index: last'   => [-1, 'o'],
            'negative index: first'  => [-5, 'h'],
            'negative index: middle' => [-3, 'l'],
        ];
    }

    #[DataProvider('provideGetValidIndices')]
    public function test_that_get_returns_correct_character(int $index, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create('hello')->get($index));
    }

    public static function provideGetOutOfRangeIndices(): array
    {
        return [
            'index equals length'         => [5],
            'index exceeds length'        => [10],
            'negative index beyond start' => [-6],
        ];
    }

    #[DataProvider('provideGetOutOfRangeIndices')]
    public function test_that_get_throws_index_exception_for_out_of_range_index(int $index): void
    {
        self::expectException(IndexException::class);

        MbStringObject::create('hello')->get($index);
    }

    // -------------------------------------------------------------------------
    // Character access: has()
    // -------------------------------------------------------------------------

    public static function provideHasIndexCases(): array
    {
        return [
            'first index'             => [0, true],
            'last index'              => [4, true],
            'negative last'           => [-1, true],
            'negative first'          => [-5, true],
            'index equals length'     => [5, false],
            'large out-of-range'      => [99, false],
            'large negative'          => [-6, false],
        ];
    }

    #[DataProvider('provideHasIndexCases')]
    public function test_that_has_returns_correct_result_for_index(int $index, bool $expected): void
    {
        self::assertSame($expected, MbStringObject::create('hello')->has($index));
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function test_that_offset_get_returns_character_at_index(): void
    {
        $str = MbStringObject::create('hello');

        self::assertSame('h', $str[0]);
        self::assertSame('o', $str[4]);
        self::assertSame('o', $str[-1]);
    }

    public function test_that_offset_exists_returns_true_for_valid_index(): void
    {
        $str = MbStringObject::create('hello');

        self::assertTrue(isset($str[0]));
        self::assertTrue(isset($str[-1]));
    }

    public function test_that_offset_exists_returns_false_for_invalid_index(): void
    {
        self::assertFalse(isset(MbStringObject::create('hello')[5]));
    }

    public function test_that_offset_set_throws_immutable_exception(): void
    {
        self::expectException(ImmutableException::class);

        $str = MbStringObject::create('hello');
        $str[0] = 'x';
    }

    public function test_that_offset_unset_throws_immutable_exception(): void
    {
        self::expectException(ImmutableException::class);

        $str = MbStringObject::create('hello');
        unset($str[0]);
    }

    // -------------------------------------------------------------------------
    // Iteration
    // -------------------------------------------------------------------------

    public function test_that_chars_returns_array_list_of_individual_characters(): void
    {
        $chars = MbStringObject::create('abc')->chars();

        self::assertInstanceOf(ArrayList::class, $chars);
        self::assertSame(3, $chars->count());
        self::assertSame('a', $chars->get(0));
        self::assertSame('b', $chars->get(1));
        self::assertSame('c', $chars->get(2));
    }

    public function test_that_chars_returns_empty_list_for_empty_string(): void
    {
        $chars = MbStringObject::create('')->chars();

        self::assertTrue($chars->isEmpty());
    }

    public function test_that_chars_correctly_splits_multibyte_string(): void
    {
        $chars = MbStringObject::create('日本語')->chars();

        self::assertSame(3, $chars->count());
        self::assertSame('日', $chars->get(0));
        self::assertSame('本', $chars->get(1));
        self::assertSame('語', $chars->get(2));
    }

    public function test_that_get_iterator_yields_each_character(): void
    {
        $collected = [];
        foreach (MbStringObject::create('abc') as $char) {
            $collected[] = $char;
        }

        self::assertSame(['a', 'b', 'c'], $collected);
    }

    // -------------------------------------------------------------------------
    // Search: contains()
    // -------------------------------------------------------------------------

    public function test_that_contains_returns_true_when_substring_is_found(): void
    {
        self::assertTrue(MbStringObject::create('hello world')->contains('world'));
    }

    public function test_that_contains_returns_false_when_substring_is_not_found(): void
    {
        self::assertFalse(MbStringObject::create('hello world')->contains('xyz'));
    }

    public function test_that_contains_is_case_sensitive_by_default(): void
    {
        self::assertFalse(MbStringObject::create('Hello')->contains('hello'));
    }

    public function test_that_contains_is_case_insensitive_when_flag_is_false(): void
    {
        self::assertTrue(MbStringObject::create('Hello')->contains('hello', false));
    }

    public function test_that_contains_returns_false_on_empty_string_value(): void
    {
        self::assertFalse(MbStringObject::create('')->contains('x'));
    }

    // -------------------------------------------------------------------------
    // Search: startsWith()
    // -------------------------------------------------------------------------

    public function test_that_starts_with_returns_true_when_string_begins_with_search(): void
    {
        self::assertTrue(MbStringObject::create('hello')->startsWith('hel'));
    }

    public function test_that_starts_with_returns_false_when_string_does_not_begin_with_search(): void
    {
        self::assertFalse(MbStringObject::create('hello')->startsWith('ell'));
    }

    public function test_that_starts_with_is_case_sensitive_by_default(): void
    {
        self::assertFalse(MbStringObject::create('Hello')->startsWith('hello'));
    }

    public function test_that_starts_with_is_case_insensitive_when_flag_is_false(): void
    {
        self::assertTrue(MbStringObject::create('Hello')->startsWith('hello', false));
    }

    public function test_that_starts_with_returns_false_for_empty_string_value(): void
    {
        self::assertFalse(MbStringObject::create('')->startsWith('x'));
    }

    public function test_that_starts_with_returns_true_for_empty_search_on_non_empty_value(): void
    {
        self::assertTrue(MbStringObject::create('hello')->startsWith(''));
    }

    // -------------------------------------------------------------------------
    // Search: endsWith()
    // -------------------------------------------------------------------------

    public function test_that_ends_with_returns_true_when_string_ends_with_search(): void
    {
        self::assertTrue(MbStringObject::create('hello')->endsWith('llo'));
    }

    public function test_that_ends_with_returns_false_when_string_does_not_end_with_search(): void
    {
        self::assertFalse(MbStringObject::create('hello')->endsWith('hel'));
    }

    public function test_that_ends_with_is_case_sensitive_by_default(): void
    {
        self::assertFalse(MbStringObject::create('Hello')->endsWith('LLO'));
    }

    public function test_that_ends_with_is_case_insensitive_when_flag_is_false(): void
    {
        self::assertTrue(MbStringObject::create('Hello')->endsWith('LLO', false));
    }

    public function test_that_ends_with_returns_false_for_empty_string_value(): void
    {
        self::assertFalse(MbStringObject::create('')->endsWith('x'));
    }

    public function test_that_ends_with_returns_true_for_empty_search_on_non_empty_value(): void
    {
        self::assertTrue(MbStringObject::create('hello')->endsWith(''));
    }

    // -------------------------------------------------------------------------
    // Search: indexOf()
    // -------------------------------------------------------------------------

    public function test_that_index_of_returns_position_of_first_occurrence(): void
    {
        self::assertSame(6, MbStringObject::create('hello world')->indexOf('world'));
    }

    public function test_that_index_of_returns_minus_one_when_not_found(): void
    {
        self::assertSame(-1, MbStringObject::create('hello')->indexOf('xyz'));
    }

    public function test_that_index_of_returns_minus_one_on_empty_string_value(): void
    {
        self::assertSame(-1, MbStringObject::create('')->indexOf('x'));
    }

    public function test_that_index_of_returns_start_offset_for_empty_search(): void
    {
        self::assertSame(0, MbStringObject::create('hello')->indexOf(''));
        self::assertSame(2, MbStringObject::create('hello')->indexOf('', 2));
    }

    public function test_that_index_of_is_case_sensitive_by_default(): void
    {
        self::assertSame(-1, MbStringObject::create('Hello')->indexOf('hello'));
    }

    public function test_that_index_of_is_case_insensitive_when_flag_is_false(): void
    {
        self::assertSame(0, MbStringObject::create('Hello')->indexOf('hello', null, false));
    }

    public function test_that_index_of_respects_start_offset(): void
    {
        // "abcabc" — first 'b' is at 1, second at 4; start from 2 → finds 4
        self::assertSame(4, MbStringObject::create('abcabc')->indexOf('b', 2));
    }

    public function test_that_index_of_throws_domain_exception_for_out_of_range_start(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->indexOf('x', 10);
    }

    // -------------------------------------------------------------------------
    // Search: lastIndexOf()
    // -------------------------------------------------------------------------

    public function test_that_last_index_of_returns_position_of_last_occurrence(): void
    {
        self::assertSame(4, MbStringObject::create('abcabc')->lastIndexOf('b'));
    }

    public function test_that_last_index_of_returns_minus_one_when_not_found(): void
    {
        self::assertSame(-1, MbStringObject::create('hello')->lastIndexOf('xyz'));
    }

    public function test_that_last_index_of_returns_minus_one_on_empty_string_value(): void
    {
        self::assertSame(-1, MbStringObject::create('')->lastIndexOf('x'));
    }

    public function test_that_last_index_of_is_case_sensitive_by_default(): void
    {
        self::assertSame(-1, MbStringObject::create('Hello')->lastIndexOf('hello'));
    }

    public function test_that_last_index_of_is_case_insensitive_when_flag_is_false(): void
    {
        self::assertSame(0, MbStringObject::create('Hello')->lastIndexOf('hello', null, false));
    }

    public function test_that_last_index_of_respects_non_zero_stop_offset(): void
    {
        // stop=5 on 'hello world hello' (len=17): prepareOffset(5,17)-17 = -12 → strrpos stops 12 from end
        self::assertSame(0, MbStringObject::create('hello world hello')->lastIndexOf('hello', 5));
    }

    public function test_that_last_index_of_returns_adjusted_position_for_empty_search_with_negative_stop(): void
    {
        // stop=-3 on 'hello' (len=5): prepareOffset(-3,5)-5 = 2-5 = -3 → $stop<0 → return -3+5 = 2
        self::assertSame(2, MbStringObject::create('hello')->lastIndexOf('', -3));
    }

    // -------------------------------------------------------------------------
    // Manipulation
    // -------------------------------------------------------------------------

    public function test_that_append_produces_concatenated_string(): void
    {
        self::assertSame('hello world', MbStringObject::create('hello')->append(' world')->value());
    }

    public function test_that_prepend_produces_concatenated_string(): void
    {
        self::assertSame('hello world', MbStringObject::create('world')->prepend('hello ')->value());
    }

    public function test_that_insert_places_string_at_given_index(): void
    {
        self::assertSame('aXbc', MbStringObject::create('abc')->insert(1, 'X')->value());
    }

    public function test_that_insert_accepts_negative_index(): void
    {
        // -1 normalises to index 2 in "abc" → inserts before 'c'
        self::assertSame('abXc', MbStringObject::create('abc')->insert(-1, 'X')->value());
    }

    public function test_that_insert_throws_domain_exception_for_out_of_range_index(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('abc')->insert(10, 'X');
    }

    public function test_that_surround_wraps_string_with_given_string(): void
    {
        self::assertSame('*hello*', MbStringObject::create('hello')->surround('*')->value());
    }

    // -------------------------------------------------------------------------
    // Padding
    // -------------------------------------------------------------------------

    public function test_that_pad_centers_string_within_given_length(): void
    {
        // "ab" → length 5: padLength=3, floor(1.5)=1 left, ceil(1.5)=2 right
        self::assertSame(' ab  ', MbStringObject::create('ab')->pad(5)->value());
    }

    public function test_that_pad_uses_custom_character(): void
    {
        self::assertSame('-ab--', MbStringObject::create('ab')->pad(5, '-')->value());
    }

    public function test_that_pad_returns_original_when_length_less_than_string_length(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->pad(3)->value());
    }

    public function test_that_pad_throws_domain_exception_for_length_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->pad(0);
    }

    public function test_that_pad_throws_domain_exception_for_multi_character_pad(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->pad(5, 'ab');
    }

    public function test_that_pad_left_pads_string_on_the_left(): void
    {
        self::assertSame('   hi', MbStringObject::create('hi')->padLeft(5)->value());
    }

    public function test_that_pad_left_uses_custom_character(): void
    {
        self::assertSame('---hi', MbStringObject::create('hi')->padLeft(5, '-')->value());
    }

    public function test_that_pad_left_throws_domain_exception_for_length_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->padLeft(0);
    }

    public function test_that_pad_left_throws_domain_exception_for_multi_character_pad(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->padLeft(5, 'ab');
    }

    public function test_that_pad_left_returns_original_when_length_is_less_than_string_length(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->padLeft(3)->value());
    }

    public function test_that_pad_right_pads_string_on_the_right(): void
    {
        self::assertSame('hi   ', MbStringObject::create('hi')->padRight(5)->value());
    }

    public function test_that_pad_right_uses_custom_character(): void
    {
        self::assertSame('hi---', MbStringObject::create('hi')->padRight(5, '-')->value());
    }

    public function test_that_pad_right_throws_domain_exception_for_length_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->padRight(0);
    }

    public function test_that_pad_right_throws_domain_exception_for_multi_character_pad(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->padRight(5, 'ab');
    }

    public function test_that_pad_right_returns_original_when_length_is_less_than_string_length(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->padRight(3)->value());
    }

    // -------------------------------------------------------------------------
    // Truncation
    // -------------------------------------------------------------------------

    public function test_that_truncate_cuts_string_to_given_length(): void
    {
        self::assertSame('hel', MbStringObject::create('hello')->truncate(3)->value());
    }

    public function test_that_truncate_appends_suffix_when_truncating(): void
    {
        // length=5, append="...(3 chars)", adjusted length=2 → "he..."
        self::assertSame('he...', MbStringObject::create('hello world')->truncate(5, '...')->value());
    }

    public function test_that_truncate_returns_value_plus_append_when_string_fits(): void
    {
        self::assertSame('hi...', MbStringObject::create('hi')->truncate(5, '...')->value());
    }

    public function test_that_truncate_throws_domain_exception_for_length_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->truncate(0);
    }

    public function test_that_truncate_throws_domain_exception_when_append_exceeds_length(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->truncate(3, '...');
    }

    public function test_that_truncate_words_truncates_at_word_boundary(): void
    {
        self::assertSame('hello', MbStringObject::create('hello world')->truncateWords(8)->value());
    }

    public function test_that_truncate_words_appends_suffix_on_truncation(): void
    {
        self::assertSame('hello...', MbStringObject::create('hello world')->truncateWords(11, '...')->value());
    }

    public function test_that_truncate_words_returns_full_string_when_it_fits(): void
    {
        self::assertSame('hi', MbStringObject::create('hi')->truncateWords(5)->value());
    }

    public function test_that_truncate_words_throws_domain_exception_for_length_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->truncateWords(0);
    }

    public function test_that_truncate_words_throws_domain_exception_when_append_exceeds_length(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello world')->truncateWords(3, '...');
    }

    public function test_that_truncate_words_truncates_mid_word_when_no_space_in_truncated_portion(): void
    {
        // 'superlongword foo' → adjusted length=5 → truncated='super', no space → returns 'super...'
        self::assertSame('super...', MbStringObject::create('superlongword foo')->truncateWords(8, '...')->value());
    }

    // -------------------------------------------------------------------------
    // Repeat
    // -------------------------------------------------------------------------

    public function test_that_repeat_returns_string_repeated_given_times(): void
    {
        self::assertSame('ababab', MbStringObject::create('ab')->repeat(3)->value());
    }

    public function test_that_repeat_with_one_returns_original_value(): void
    {
        self::assertSame('hello', MbStringObject::create('hello')->repeat(1)->value());
    }

    public function test_that_repeat_throws_domain_exception_for_zero_multiplier(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->repeat(0);
    }

    public function test_that_repeat_throws_domain_exception_for_negative_multiplier(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hi')->repeat(-1);
    }

    // -------------------------------------------------------------------------
    // Slicing
    // -------------------------------------------------------------------------

    public function test_that_slice_returns_substring_between_start_and_stop(): void
    {
        // slice(1, 4) on "hello" → offset=1, length=4-1=3 → "ell"
        self::assertSame('ell', MbStringObject::create('hello')->slice(1, 4)->value());
    }

    public function test_that_slice_with_no_stop_returns_from_start_to_end(): void
    {
        self::assertSame('ello', MbStringObject::create('hello')->slice(1)->value());
    }

    public function test_that_slice_accepts_negative_start(): void
    {
        self::assertSame('lo', MbStringObject::create('hello')->slice(-2)->value());
    }

    public function test_that_substr_returns_substring_from_start_with_given_length(): void
    {
        self::assertSame('ell', MbStringObject::create('hello')->substr(1, 3)->value());
    }

    public function test_that_substr_with_no_length_returns_from_start_to_end(): void
    {
        self::assertSame('ello', MbStringObject::create('hello')->substr(1)->value());
    }

    public function test_that_substr_accepts_negative_start(): void
    {
        self::assertSame('lo', MbStringObject::create('hello')->substr(-2)->value());
    }

    // -------------------------------------------------------------------------
    // Splitting
    // -------------------------------------------------------------------------

    public function test_that_split_splits_string_on_delimiter(): void
    {
        $parts = MbStringObject::create('a,b,c')->split(',');

        self::assertSame(3, $parts->count());
        self::assertSame('a', (string) $parts->get(0));
        self::assertSame('b', (string) $parts->get(1));
        self::assertSame('c', (string) $parts->get(2));
    }

    public function test_that_split_respects_limit(): void
    {
        $parts = MbStringObject::create('a,b,c')->split(',', 2);

        self::assertSame(2, $parts->count());
    }

    public function test_that_split_throws_domain_exception_for_empty_delimiter(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->split('');
    }

    public function test_that_chunk_splits_string_into_pieces_of_given_size(): void
    {
        $chunks = MbStringObject::create('abcde')->chunk(2);

        self::assertSame(3, $chunks->count());
        self::assertSame('ab', (string) $chunks->get(0));
        self::assertSame('cd', (string) $chunks->get(1));
        self::assertSame('e', (string) $chunks->get(2));
    }

    public function test_that_chunk_returns_single_element_when_size_exceeds_length(): void
    {
        $chunks = MbStringObject::create('hi')->chunk(10);

        self::assertSame(1, $chunks->count());
        self::assertSame('hi', (string) $chunks->get(0));
    }

    public function test_that_chunk_throws_domain_exception_for_size_less_than_one(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create('hello')->chunk(0);
    }

    // -------------------------------------------------------------------------
    // Replace and Trim
    // -------------------------------------------------------------------------

    public function test_that_replace_replaces_all_occurrences_of_search(): void
    {
        self::assertSame('hXllX', MbStringObject::create('hello')->replace('e', 'X')->replace('o', 'X')->value());
    }

    public function test_that_replace_accepts_array_search_and_replace(): void
    {
        self::assertSame('hXllY', MbStringObject::create('hello')->replace(['e', 'o'], ['X', 'Y'])->value());
    }

    public function test_that_trim_removes_surrounding_whitespace(): void
    {
        self::assertSame('hello', MbStringObject::create('  hello  ')->trim()->value());
    }

    public function test_that_trim_removes_custom_mask_characters(): void
    {
        self::assertSame('hello', MbStringObject::create('--hello--')->trim('-')->value());
    }

    public function test_that_trim_left_removes_only_leading_whitespace(): void
    {
        self::assertSame('hello  ', MbStringObject::create('  hello  ')->trimLeft()->value());
    }

    public function test_that_trim_right_removes_only_trailing_whitespace(): void
    {
        self::assertSame('  hello', MbStringObject::create('  hello  ')->trimRight()->value());
    }

    // -------------------------------------------------------------------------
    // Tab expansion
    // -------------------------------------------------------------------------

    public function test_that_expand_tabs_replaces_tab_with_four_spaces_by_default(): void
    {
        self::assertSame('    hello', MbStringObject::create("\thello")->expandTabs()->value());
    }

    public function test_that_expand_tabs_uses_given_tab_size(): void
    {
        self::assertSame('  hello', MbStringObject::create("\thello")->expandTabs(2)->value());
    }

    public function test_that_expand_tabs_with_zero_removes_tabs(): void
    {
        self::assertSame('hello', MbStringObject::create("\thello")->expandTabs(0)->value());
    }

    public function test_that_expand_tabs_throws_domain_exception_for_negative_tab_size(): void
    {
        self::expectException(DomainException::class);

        MbStringObject::create("\thello")->expandTabs(-1);
    }

    // -------------------------------------------------------------------------
    // Case conversion
    // -------------------------------------------------------------------------

    public function test_that_to_lower_case_converts_entire_string_to_lowercase(): void
    {
        self::assertSame('hello world', MbStringObject::create('Hello World')->toLowerCase()->value());
    }

    public function test_that_to_upper_case_converts_entire_string_to_uppercase(): void
    {
        self::assertSame('HELLO WORLD', MbStringObject::create('Hello World')->toUpperCase()->value());
    }

    public function test_that_to_first_lower_case_lowercases_only_first_character(): void
    {
        self::assertSame('hELLO', MbStringObject::create('HELLO')->toFirstLowerCase()->value());
    }

    public function test_that_to_first_lower_case_returns_empty_string_unchanged(): void
    {
        self::assertSame('', MbStringObject::create('')->toFirstLowerCase()->value());
    }

    public function test_that_to_first_lower_case_lowercases_single_character_string(): void
    {
        self::assertSame('h', MbStringObject::create('H')->toFirstLowerCase()->value());
    }

    public function test_that_to_first_upper_case_uppercases_only_first_character(): void
    {
        self::assertSame('Hello', MbStringObject::create('hello')->toFirstUpperCase()->value());
    }

    public function test_that_to_first_upper_case_returns_empty_string_unchanged(): void
    {
        self::assertSame('', MbStringObject::create('')->toFirstUpperCase()->value());
    }

    public function test_that_to_first_upper_case_uppercases_single_character_string(): void
    {
        self::assertSame('H', MbStringObject::create('h')->toFirstUpperCase()->value());
    }

    public static function provideCamelCaseCases(): array
    {
        return [
            'space-separated words' => ['hello world', 'helloWorld'],
            'snake_case input'      => ['hello_world', 'helloWorld'],
            'hyphenated input'      => ['hello-world', 'helloWorld'],
            'PascalCase input'      => ['HelloWorld', 'helloWorld'],
            'empty string'          => ['', ''],
        ];
    }

    #[DataProvider('provideCamelCaseCases')]
    public function test_that_to_camel_case_converts_string_to_camel_case(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toCamelCase()->value());
    }

    public static function providePascalCaseCases(): array
    {
        return [
            'space-separated words' => ['hello world', 'HelloWorld'],
            'snake_case input'      => ['hello_world', 'HelloWorld'],
            'hyphenated input'      => ['hello-world', 'HelloWorld'],
            'camelCase input'       => ['helloWorld', 'HelloWorld'],
            'empty string'          => ['', ''],
        ];
    }

    #[DataProvider('providePascalCaseCases')]
    public function test_that_to_pascal_case_converts_string_to_pascal_case(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toPascalCase()->value());
    }

    public function test_that_to_pascal_case_capitalises_each_single_character_word(): void
    {
        // Parts 'a', 'b', 'c' each have length 1 → exercises the single-char else branch in capsCase
        self::assertSame('ABC', MbStringObject::create('a b c')->toPascalCase()->value());
    }

    public static function provideSnakeCaseCases(): array
    {
        return [
            'space-separated words' => ['hello world', 'hello_world'],
            'PascalCase input'      => ['HelloWorld', 'hello_world'],
            'camelCase input'       => ['helloWorld', 'hello_world'],
            'hyphenated input'      => ['hello-world', 'hello_world'],
            'empty string'          => ['', ''],
        ];
    }

    #[DataProvider('provideSnakeCaseCases')]
    public function test_that_to_snake_case_converts_string_to_snake_case(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toSnakeCase()->value());
    }

    public static function provideLowerHyphenatedCases(): array
    {
        return [
            'PascalCase input'      => ['HelloWorld', 'hello-world'],
            'camelCase input'       => ['helloWorld', 'hello-world'],
            'snake_case input'      => ['hello_world', 'hello-world'],
            'space-separated words' => ['hello world', 'hello-world'],
            'empty string'          => ['', ''],
        ];
    }

    #[DataProvider('provideLowerHyphenatedCases')]
    public function test_that_to_lower_hyphenated_converts_string_correctly(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toLowerHyphenated()->value());
    }

    public static function provideUpperHyphenatedCases(): array
    {
        return [
            'PascalCase input'      => ['HelloWorld', 'HELLO-WORLD'],
            'snake_case input'      => ['hello_world', 'HELLO-WORLD'],
            'empty string'          => ['', ''],
        ];
    }

    #[DataProvider('provideUpperHyphenatedCases')]
    public function test_that_to_upper_hyphenated_converts_string_correctly(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toUpperHyphenated()->value());
    }

    public static function provideLowerUnderscoredCases(): array
    {
        return [
            'PascalCase input' => ['HelloWorld', 'hello_world'],
            'hyphenated input' => ['hello-world', 'hello_world'],
            'empty string'     => ['', ''],
        ];
    }

    #[DataProvider('provideLowerUnderscoredCases')]
    public function test_that_to_lower_underscored_converts_string_correctly(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toLowerUnderscored()->value());
    }

    public static function provideUpperUnderscoredCases(): array
    {
        return [
            'PascalCase input' => ['HelloWorld', 'HELLO_WORLD'],
            'hyphenated input' => ['hello-world', 'HELLO_WORLD'],
            'empty string'     => ['', ''],
        ];
    }

    #[DataProvider('provideUpperUnderscoredCases')]
    public function test_that_to_upper_underscored_converts_string_correctly(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toUpperUnderscored()->value());
    }

    public static function provideSlugCases(): array
    {
        return [
            'space-separated words'    => ['Hello World', 'hello-world'],
            'multiple spaces'          => ['hello  world', 'hello-world'],
            'surrounding spaces'       => ['  hello  ', 'hello'],
            'underscore is preserved'  => ['hello_world-foo', 'hello_world-foo'],
            'already lowercase slug'   => ['hello-world', 'hello-world'],
        ];
    }

    #[DataProvider('provideSlugCases')]
    public function test_that_to_slug_converts_string_to_url_segment(string $input, string $expected): void
    {
        self::assertSame($expected, MbStringObject::create($input)->toSlug()->value());
    }

    // -------------------------------------------------------------------------
    // Immutability
    // -------------------------------------------------------------------------

    public function test_that_transformations_return_new_instances(): void
    {
        $original = MbStringObject::create('hello');

        self::assertNotSame($original, $original->append(' world'));
        self::assertNotSame($original, $original->prepend('say '));
        self::assertNotSame($original, $original->toUpperCase());
        self::assertNotSame($original, $original->toLowerCase());
        self::assertNotSame($original, $original->trim());
        self::assertNotSame($original, $original->repeat(2));
    }

    public function test_that_transformations_do_not_modify_original_value(): void
    {
        $original = MbStringObject::create('hello');
        $original->append(' world');
        $original->toUpperCase();
        $original->repeat(3);

        self::assertSame('hello', $original->value());
    }
}
