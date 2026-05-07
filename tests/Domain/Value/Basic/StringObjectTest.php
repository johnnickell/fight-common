<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Value\Basic;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\ImmutableException;
use Fight\Common\Domain\Exception\IndexException;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StringObject::class)]
class StringObjectTest extends UnitTestCase
{
    // -------------------------------------------------------------------------
    // Creation and basic accessors
    // -------------------------------------------------------------------------

    public function test_that_create_returns_instance_with_value(): void
    {
        $str = StringObject::create('hello');

        self::assertSame('hello', $str->value());
    }

    public function test_that_from_string_returns_instance_with_value(): void
    {
        $str = StringObject::fromString('world');

        self::assertSame('world', $str->value());
    }

    public function test_that_length_returns_character_count(): void
    {
        self::assertSame(5, StringObject::create('hello')->length());
        self::assertSame(0, StringObject::create('')->length());
    }

    public function test_that_is_empty_returns_true_for_empty_string(): void
    {
        self::assertTrue(StringObject::create('')->isEmpty());
    }

    public function test_that_is_empty_returns_false_for_non_empty_string(): void
    {
        self::assertFalse(StringObject::create('a')->isEmpty());
    }

    public function test_that_count_returns_character_count(): void
    {
        self::assertSame(5, StringObject::create('hello')->count());
    }

    // -------------------------------------------------------------------------
    // Character access
    // -------------------------------------------------------------------------

    public function test_that_get_returns_character_at_index(): void
    {
        $str = StringObject::create('hello');

        self::assertSame('h', $str->get(0));
        self::assertSame('e', $str->get(1));
        self::assertSame('o', $str->get(4));
    }

    public function test_that_get_supports_negative_index(): void
    {
        $str = StringObject::create('hello');

        self::assertSame('o', $str->get(-1));
        self::assertSame('h', $str->get(-5));
    }

    public function test_that_get_throws_for_out_of_range_index(): void
    {
        $str = StringObject::create('hello');

        $this->expectException(IndexException::class);
        $str->get(10);
    }

    public function test_that_has_returns_true_for_valid_index(): void
    {
        $str = StringObject::create('hello');

        self::assertTrue($str->has(0));
        self::assertTrue($str->has(4));
        self::assertTrue($str->has(-1));
        self::assertTrue($str->has(-5));
    }

    public function test_that_has_returns_false_for_invalid_index(): void
    {
        $str = StringObject::create('hello');

        self::assertFalse($str->has(5));
        self::assertFalse($str->has(-6));
    }

    public function test_that_offset_get_returns_character_at_index(): void
    {
        $str = StringObject::create('hello');

        self::assertSame('h', $str[0]);
        self::assertSame('o', $str[4]);
    }

    public function test_that_offset_exists_returns_true_for_valid_index(): void
    {
        $str = StringObject::create('hello');

        self::assertTrue(isset($str[0]));
        self::assertFalse(isset($str[10]));
    }

    public function test_that_offset_set_throws_immutable_exception(): void
    {
        $str = StringObject::create('hello');

        $this->expectException(ImmutableException::class);
        $str[0] = 'x';
    }

    public function test_that_offset_unset_throws_immutable_exception(): void
    {
        $str = StringObject::create('hello');

        $this->expectException(ImmutableException::class);
        unset($str[0]);
    }

    // -------------------------------------------------------------------------
    // Searching
    // -------------------------------------------------------------------------

    public function test_that_contains_returns_true_when_search_found(): void
    {
        $str = StringObject::create('hello world');

        self::assertTrue($str->contains('world'));
        self::assertTrue($str->contains(''));
    }

    public function test_that_contains_returns_false_when_search_not_found(): void
    {
        $str = StringObject::create('hello world');

        self::assertFalse($str->contains('xyz'));
    }

    public function test_that_contains_is_case_insensitive_when_specified(): void
    {
        $str = StringObject::create('Hello World');

        self::assertTrue($str->contains('hello', false));
        self::assertTrue($str->contains('WORLD', false));
    }

    public function test_that_contains_returns_false_for_empty_string(): void
    {
        self::assertFalse(StringObject::create('')->contains('hello'));
    }

    public function test_that_starts_with_returns_true_for_matching_prefix(): void
    {
        $str = StringObject::create('hello world');

        self::assertTrue($str->startsWith('hello'));
    }

    public function test_that_starts_with_returns_false_for_non_matching_prefix(): void
    {
        $str = StringObject::create('hello world');

        self::assertFalse($str->startsWith('world'));
    }

    public function test_that_starts_with_returns_false_for_empty_string_value(): void
    {
        self::assertFalse(StringObject::create('')->startsWith('hello'));
    }

    public function test_that_starts_with_returns_true_for_empty_search_on_non_empty_value(): void
    {
        self::assertTrue(StringObject::create('hello')->startsWith(''));
    }

    public function test_that_starts_with_is_case_insensitive_when_specified(): void
    {
        self::assertTrue(StringObject::create('Hello')->startsWith('hello', false));
    }

    public function test_that_ends_with_returns_true_for_matching_suffix(): void
    {
        $str = StringObject::create('hello world');

        self::assertTrue($str->endsWith('world'));
    }

    public function test_that_ends_with_returns_false_for_non_matching_suffix(): void
    {
        $str = StringObject::create('hello world');

        self::assertFalse($str->endsWith('hello'));
    }

    public function test_that_ends_with_returns_false_for_empty_string_value(): void
    {
        self::assertFalse(StringObject::create('')->endsWith('hello'));
    }

    public function test_that_ends_with_returns_true_for_empty_search_on_non_empty_value(): void
    {
        self::assertTrue(StringObject::create('hello')->endsWith(''));
    }

    public function test_that_ends_with_is_case_insensitive_when_specified(): void
    {
        self::assertTrue(StringObject::create('hello WORLD')->endsWith('world', false));
    }

    public function test_that_index_of_returns_first_position_of_search(): void
    {
        $str = StringObject::create('hello world hello');

        self::assertSame(0, $str->indexOf('hello'));
        self::assertSame(6, $str->indexOf('world'));
    }

    public function test_that_index_of_returns_negative_one_when_not_found(): void
    {
        self::assertSame(-1, StringObject::create('hello')->indexOf('xyz'));
    }

    public function test_that_index_of_returns_negative_one_for_empty_string_value(): void
    {
        self::assertSame(-1, StringObject::create('')->indexOf('hello'));
    }

    public function test_that_index_of_returns_start_for_empty_search(): void
    {
        self::assertSame(0, StringObject::create('hello')->indexOf(''));
    }

    public function test_that_index_of_is_case_insensitive_when_specified(): void
    {
        self::assertSame(0, StringObject::create('Hello World')->indexOf('hello', null, false));
    }

    public function test_that_last_index_of_returns_last_position_of_search(): void
    {
        $str = StringObject::create('hello world hello');

        self::assertSame(12, $str->lastIndexOf('hello'));
    }

    public function test_that_last_index_of_returns_negative_one_when_not_found(): void
    {
        self::assertSame(-1, StringObject::create('hello')->lastIndexOf('xyz'));
    }

    public function test_that_last_index_of_returns_negative_one_for_empty_string_value(): void
    {
        self::assertSame(-1, StringObject::create('')->lastIndexOf('hello'));
    }

    public function test_that_last_index_of_respects_non_zero_stop_offset(): void
    {
        // stop=5 on 'hello world hello' (len=17) limits the search to the first 5 chars → finds index 0
        self::assertSame(0, StringObject::create('hello world hello')->lastIndexOf('hello', 5));
    }

    public function test_that_last_index_of_returns_adjusted_position_for_empty_search_with_negative_stop(): void
    {
        // stop=-3 on 'hello' (len=5): prepareOffset(-3,5)-5 = 2-5 = -3 → $stop<0 → return -3+5 = 2
        self::assertSame(2, StringObject::create('hello')->lastIndexOf('', -3));
    }

    public function test_that_last_index_of_is_case_insensitive_when_specified(): void
    {
        self::assertSame(12, StringObject::create('hello world HELLO')->lastIndexOf('hello', null, false));
    }

    // -------------------------------------------------------------------------
    // Building
    // -------------------------------------------------------------------------

    public function test_that_append_adds_string_to_end(): void
    {
        $str = StringObject::create('hello')->append(' world');

        self::assertSame('hello world', $str->toString());
    }

    public function test_that_prepend_adds_string_to_start(): void
    {
        $str = StringObject::create('world')->prepend('hello ');

        self::assertSame('hello world', $str->toString());
    }

    public function test_that_insert_inserts_string_at_index(): void
    {
        $str = StringObject::create('helo')->insert(3, 'l');

        self::assertSame('hello', $str->toString());
    }

    public function test_that_surround_wraps_string_with_given_string(): void
    {
        $str = StringObject::create('hello')->surround('*');

        self::assertSame('*hello*', $str->toString());
    }

    // -------------------------------------------------------------------------
    // Padding
    // -------------------------------------------------------------------------

    public function test_that_pad_centers_string_to_given_length(): void
    {
        $str = StringObject::create('hi')->pad(6);

        self::assertSame('  hi  ', $str->toString());
    }

    public function test_that_pad_uses_custom_character(): void
    {
        $str = StringObject::create('hi')->pad(6, '-');

        self::assertSame('--hi--', $str->toString());
    }

    public function test_that_pad_returns_original_when_length_is_not_greater(): void
    {
        $str = StringObject::create('hello')->pad(3);

        self::assertSame('hello', $str->toString());
    }

    public function test_that_pad_throws_for_invalid_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->pad(0);
    }

    public function test_that_pad_throws_for_invalid_character(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->pad(6, 'ab');
    }

    public function test_that_pad_left_pads_string_on_left(): void
    {
        $str = StringObject::create('hi')->padLeft(5);

        self::assertSame('   hi', $str->toString());
    }

    public function test_that_pad_left_throws_for_invalid_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->padLeft(0);
    }

    public function test_that_pad_left_throws_for_invalid_character(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->padLeft(6, 'ab');
    }

    public function test_that_pad_left_returns_original_when_length_is_not_greater(): void
    {
        self::assertSame('hello', StringObject::create('hello')->padLeft(3)->toString());
    }

    public function test_that_pad_right_pads_string_on_right(): void
    {
        $str = StringObject::create('hi')->padRight(5);

        self::assertSame('hi   ', $str->toString());
    }

    public function test_that_pad_right_throws_for_invalid_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->padRight(0);
    }

    public function test_that_pad_right_throws_for_invalid_character(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hi')->padRight(6, 'ab');
    }

    public function test_that_pad_right_returns_original_when_length_is_not_greater(): void
    {
        self::assertSame('hello', StringObject::create('hello')->padRight(3)->toString());
    }

    // -------------------------------------------------------------------------
    // Truncation
    // -------------------------------------------------------------------------

    public function test_that_truncate_shortens_string_to_given_length(): void
    {
        $str = StringObject::create('hello world')->truncate(5);

        self::assertSame('hello', $str->toString());
    }

    public function test_that_truncate_appends_string_on_truncation(): void
    {
        $str = StringObject::create('hello world')->truncate(8, '...');

        self::assertSame('hello...', $str->toString());
    }

    public function test_that_truncate_returns_original_when_not_truncated(): void
    {
        $str = StringObject::create('hi')->truncate(5, '...');

        self::assertSame('hi...', $str->toString());
    }

    public function test_that_truncate_throws_for_invalid_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello')->truncate(0);
    }

    public function test_that_truncate_throws_when_append_too_long_for_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello world')->truncate(3, '...');
    }

    public function test_that_truncate_words_preserves_word_boundaries(): void
    {
        $str = StringObject::create('hello world foo')->truncateWords(12, '...');

        self::assertSame('hello...', $str->toString());
    }

    public function test_that_truncate_words_throws_for_invalid_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello world')->truncateWords(0);
    }

    public function test_that_truncate_words_throws_when_append_too_long_for_length(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello world')->truncateWords(3, '...');
    }

    public function test_that_truncate_words_returns_original_with_append_when_not_truncated(): void
    {
        self::assertSame('hi...', StringObject::create('hi')->truncateWords(5, '...')->toString());
    }

    public function test_that_truncate_words_truncates_mid_word_when_no_space_in_truncated_portion(): void
    {
        // 'superlongword foo' → adjusted length=5 → truncated='super', no space → returns 'super...'
        self::assertSame('super...', StringObject::create('superlongword foo')->truncateWords(8, '...')->toString());
    }

    // -------------------------------------------------------------------------
    // Repeat and slicing
    // -------------------------------------------------------------------------

    public function test_that_repeat_creates_repeated_string(): void
    {
        $str = StringObject::create('ab')->repeat(3);

        self::assertSame('ababab', $str->toString());
    }

    public function test_that_repeat_throws_for_invalid_multiplier(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('ab')->repeat(0);
    }

    public function test_that_slice_returns_substring_between_indexes(): void
    {
        $str = StringObject::create('hello world');

        self::assertSame('hello', $str->slice(0, 5)->toString());
        self::assertSame('world', $str->slice(6)->toString());
    }

    public function test_that_slice_with_negative_stop_returns_substring_from_end(): void
    {
        self::assertSame('hell', StringObject::create('hello')->slice(0, -1)->toString());
    }

    public function test_that_slice_with_stop_beyond_length_returns_full_remainder(): void
    {
        self::assertSame('hello', StringObject::create('hello')->slice(0, 10)->toString());
    }

    public function test_that_slice_throws_for_negative_stop_out_of_range(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello')->slice(0, -10);
    }

    public function test_that_substr_returns_substring_from_index(): void
    {
        $str = StringObject::create('hello world');

        self::assertSame('world', $str->substr(6)->toString());
        self::assertSame('hel', $str->substr(0, 3)->toString());
    }

    public function test_that_substr_with_negative_length_returns_substring_from_end(): void
    {
        self::assertSame('hell', StringObject::create('hello')->substr(0, -1)->toString());
    }

    public function test_that_substr_with_length_beyond_string_returns_full_remainder(): void
    {
        self::assertSame('hello', StringObject::create('hello')->substr(0, 10)->toString());
    }

    public function test_that_substr_throws_for_negative_length_out_of_range(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello')->substr(0, -10);
    }

    // -------------------------------------------------------------------------
    // Split and chunk
    // -------------------------------------------------------------------------

    public function test_that_split_divides_string_by_delimiter(): void
    {
        $list = StringObject::create('a,b,c')->split(',');

        self::assertSame(3, $list->count());
    }

    public function test_that_split_with_limit_returns_at_most_limit_parts(): void
    {
        $list = StringObject::create('a,b,c')->split(',', 2);

        self::assertSame(2, $list->count());
    }

    public function test_that_split_throws_for_empty_delimiter(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello')->split('');
    }

    public function test_that_chunk_divides_string_into_equal_parts(): void
    {
        $list = StringObject::create('abcdef')->chunk(2);

        self::assertSame(3, $list->count());
    }

    public function test_that_chunk_throws_for_invalid_size(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create('hello')->chunk(0);
    }

    // -------------------------------------------------------------------------
    // Replacing and trimming
    // -------------------------------------------------------------------------

    public function test_that_replace_replaces_search_with_replacement(): void
    {
        $str = StringObject::create('hello world')->replace('world', 'there');

        self::assertSame('hello there', $str->toString());
    }

    public function test_that_trim_removes_whitespace_from_both_ends(): void
    {
        $str = StringObject::create('  hello  ')->trim();

        self::assertSame('hello', $str->toString());
    }

    public function test_that_trim_removes_mask_characters(): void
    {
        $str = StringObject::create('***hello***')->trim('*');

        self::assertSame('hello', $str->toString());
    }

    public function test_that_trim_left_removes_whitespace_from_left(): void
    {
        $str = StringObject::create('  hello  ')->trimLeft();

        self::assertSame('hello  ', $str->toString());
    }

    public function test_that_trim_left_removes_mask_characters(): void
    {
        self::assertSame('hello***', StringObject::create('***hello***')->trimLeft('*')->toString());
    }

    public function test_that_trim_right_removes_whitespace_from_right(): void
    {
        $str = StringObject::create('  hello  ')->trimRight();

        self::assertSame('  hello', $str->toString());
    }

    public function test_that_trim_right_removes_mask_characters(): void
    {
        self::assertSame('***hello', StringObject::create('***hello***')->trimRight('*')->toString());
    }

    public function test_that_expand_tabs_replaces_tabs_with_spaces(): void
    {
        $str = StringObject::create("a\tb")->expandTabs(4);

        self::assertSame('a    b', $str->toString());
    }

    public function test_that_expand_tabs_throws_for_negative_tab_size(): void
    {
        $this->expectException(DomainException::class);
        StringObject::create("a\tb")->expandTabs(-1);
    }

    // -------------------------------------------------------------------------
    // Case transformations
    // -------------------------------------------------------------------------

    public function test_that_to_lower_case_converts_to_lowercase(): void
    {
        self::assertSame('hello world', StringObject::create('HELLO WORLD')->toLowerCase()->toString());
    }

    public function test_that_to_upper_case_converts_to_uppercase(): void
    {
        self::assertSame('HELLO WORLD', StringObject::create('hello world')->toUpperCase()->toString());
    }

    public function test_that_to_first_lower_case_lowercases_first_character(): void
    {
        self::assertSame('hELLO', StringObject::create('HELLO')->toFirstLowerCase()->toString());
    }

    public function test_that_to_first_upper_case_uppercases_first_character(): void
    {
        self::assertSame('Hello', StringObject::create('hello')->toFirstUpperCase()->toString());
    }

    public function test_that_to_camel_case_converts_snake_case(): void
    {
        self::assertSame('helloWorld', StringObject::create('hello_world')->toCamelCase()->toString());
    }

    public function test_that_to_camel_case_converts_hyphenated(): void
    {
        self::assertSame('helloWorld', StringObject::create('hello-world')->toCamelCase()->toString());
    }

    public function test_that_to_camel_case_converts_spaces(): void
    {
        self::assertSame('helloWorld', StringObject::create('hello world')->toCamelCase()->toString());
    }

    public function test_that_to_camel_case_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toCamelCase()->toString());
    }

    public function test_that_to_pascal_case_converts_snake_case(): void
    {
        self::assertSame('HelloWorld', StringObject::create('hello_world')->toPascalCase()->toString());
    }

    public function test_that_to_pascal_case_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toPascalCase()->toString());
    }

    public function test_that_to_pascal_case_capitalises_each_single_character_word(): void
    {
        // Parts 'a','b','c' each have length 1 → exercises the single-char else branch in capsCase
        self::assertSame('ABC', StringObject::create('a b c')->toPascalCase()->toString());
    }

    public function test_that_to_snake_case_converts_camel_case(): void
    {
        self::assertSame('hello_world', StringObject::create('helloWorld')->toSnakeCase()->toString());
    }

    public function test_that_to_snake_case_converts_spaces(): void
    {
        self::assertSame('hello_world', StringObject::create('hello world')->toSnakeCase()->toString());
    }

    public function test_that_to_snake_case_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toSnakeCase()->toString());
    }

    public function test_that_to_lower_hyphenated_converts_camel_case(): void
    {
        self::assertSame('hello-world', StringObject::create('helloWorld')->toLowerHyphenated()->toString());
    }

    public function test_that_to_lower_hyphenated_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toLowerHyphenated()->toString());
    }

    public function test_that_to_upper_hyphenated_converts_to_uppercase_hyphenated(): void
    {
        self::assertSame('HELLO-WORLD', StringObject::create('helloWorld')->toUpperHyphenated()->toString());
    }

    public function test_that_to_upper_hyphenated_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toUpperHyphenated()->toString());
    }

    public function test_that_to_lower_underscored_converts_camel_case(): void
    {
        self::assertSame('hello_world', StringObject::create('helloWorld')->toLowerUnderscored()->toString());
    }

    public function test_that_to_lower_underscored_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toLowerUnderscored()->toString());
    }

    public function test_that_to_upper_underscored_converts_to_uppercase_underscored(): void
    {
        self::assertSame('HELLO_WORLD', StringObject::create('helloWorld')->toUpperUnderscored()->toString());
    }

    public function test_that_to_upper_underscored_returns_empty_for_blank_string(): void
    {
        self::assertSame('', StringObject::create('')->toUpperUnderscored()->toString());
    }

    public function test_that_to_slug_converts_to_url_safe_string(): void
    {
        self::assertSame('hello-world', StringObject::create('Hello World')->toSlug()->toString());
        self::assertSame('hello-world', StringObject::create('  Hello--World!  ')->toSlug()->toString());
    }

    // -------------------------------------------------------------------------
    // chars and iteration
    // -------------------------------------------------------------------------

    public function test_that_chars_returns_list_of_characters(): void
    {
        $list = StringObject::create('abc')->chars();

        self::assertSame(3, $list->count());
    }

    public function test_that_get_iterator_returns_traversable(): void
    {
        $str = StringObject::create('abc');
        $chars = [];

        foreach ($str as $char) {
            $chars[] = $char;
        }

        self::assertSame(['a', 'b', 'c'], $chars);
    }

    // -------------------------------------------------------------------------
    // Value interface
    // -------------------------------------------------------------------------

    public function test_that_to_string_returns_raw_value(): void
    {
        self::assertSame('hello', StringObject::create('hello')->toString());
    }

    public function test_that_cast_to_string_returns_raw_value(): void
    {
        self::assertSame('hello', (string) StringObject::create('hello'));
    }

    public function test_that_json_serialize_returns_raw_value(): void
    {
        $str = StringObject::create('hello');

        self::assertSame(json_encode('hello'), json_encode($str));
    }

    public function test_that_equals_returns_true_for_identical_values(): void
    {
        $str1 = StringObject::create('hello');
        $str2 = StringObject::create('hello');

        self::assertTrue($str1->equals($str2));
    }

    public function test_that_equals_returns_false_for_different_values(): void
    {
        $str1 = StringObject::create('hello');
        $str2 = StringObject::create('world');

        self::assertFalse($str1->equals($str2));
    }

    public function test_that_equals_returns_false_for_different_type(): void
    {
        $str = StringObject::create('hello');

        self::assertFalse($str->equals('hello'));
    }

    public function test_that_hash_value_returns_string_value(): void
    {
        self::assertSame('hello', StringObject::create('hello')->hashValue());
    }

    // -------------------------------------------------------------------------
    // Comparable
    // -------------------------------------------------------------------------

    public function test_that_compare_to_returns_zero_for_equal_strings(): void
    {
        $str1 = StringObject::create('hello');
        $str2 = StringObject::create('hello');

        self::assertSame(0, $str1->compareTo($str2));
    }

    public function test_that_compare_to_returns_negative_for_lesser_string(): void
    {
        $str1 = StringObject::create('apple');
        $str2 = StringObject::create('banana');

        self::assertLessThan(0, $str1->compareTo($str2));
    }

    public function test_that_compare_to_returns_positive_for_greater_string(): void
    {
        $str1 = StringObject::create('banana');
        $str2 = StringObject::create('apple');

        self::assertGreaterThan(0, $str1->compareTo($str2));
    }

    public function test_that_compare_to_returns_zero_for_same_instance(): void
    {
        $str = StringObject::create('hello');

        self::assertSame(0, $str->compareTo($str));
    }
}
