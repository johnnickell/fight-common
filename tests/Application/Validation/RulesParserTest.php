<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation;

use Fight\Common\Application\Validation\RulesParser;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RulesParser::class)]
class RulesParserTest extends UnitTestCase
{
    public function test_that_rule_with_no_arguments_is_parsed_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'email', 'label' => 'Email', 'rules' => 'email'],
        ]);

        self::assertSame([
            'email' => [
                ['type' => 'Email', 'args' => [], 'error' => 'Email must be a valid email address'],
            ],
        ], $result);
    }

    public function test_that_rule_with_single_argument_extracts_the_argument_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'name', 'label' => 'Name', 'rules' => 'min_length[5]'],
        ]);

        self::assertSame([
            'name' => [
                ['type' => 'MinLength', 'args' => ['5'], 'error' => 'Name must contain no less than 5 characters'],
            ],
        ], $result);
    }

    public function test_that_rule_with_multiple_arguments_extracts_all_arguments_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'name', 'label' => 'Name', 'rules' => 'range_length[3,10]'],
        ]);

        self::assertSame([
            'name' => [
                ['type' => 'RangeLength', 'args' => ['3', '10'], 'error' => 'Name must contain between 3 and 10 characters'],
            ],
        ], $result);
    }

    public function test_that_multiple_rules_on_a_single_field_are_all_parsed(): void
    {
        $result = RulesParser::parse([
            ['field' => 'email', 'label' => 'Email', 'rules' => 'required|email'],
        ]);

        self::assertSame([
            'email' => [
                ['type' => 'Required', 'args' => [], 'error' => 'Email is required'],
                ['type' => 'Email',    'args' => [], 'error' => 'Email must be a valid email address'],
            ],
        ], $result);
    }

    public function test_that_multiple_field_definitions_are_all_parsed(): void
    {
        $result = RulesParser::parse([
            ['field' => 'username', 'label' => 'Username', 'rules' => 'required'],
            ['field' => 'email',    'label' => 'Email',    'rules' => 'required'],
        ]);

        self::assertArrayHasKey('username', $result);
        self::assertArrayHasKey('email', $result);
        self::assertCount(1, $result['username']);
        self::assertCount(1, $result['email']);
    }

    public function test_that_date_format_rule_is_parsed_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'published_at', 'label' => 'Published At', 'rules' => 'date[Y-m-d]'],
        ]);

        self::assertSame([
            'published_at' => [
                ['type' => 'Date', 'args' => ['Y-m-d'], 'error' => 'Published At must be a valid date in format "Y-m-d"'],
            ],
        ], $result);
    }

    public function test_that_time_format_rule_is_parsed_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'start_time', 'label' => 'Start Time', 'rules' => 'time[H:i:s]'],
        ]);

        self::assertSame([
            'start_time' => [
                ['type' => 'Time', 'args' => ['H:i:s'], 'error' => 'Start Time must be a valid time in format "H:i:s"'],
            ],
        ], $result);
    }

    public function test_that_date_time_format_rule_is_parsed_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'created_at', 'label' => 'Created At', 'rules' => 'date_time[Y-m-d H:i:s]'],
        ]);

        self::assertSame([
            'created_at' => [
                ['type' => 'DateTime', 'args' => ['Y-m-d H:i:s'], 'error' => 'Created At must be a valid date/time in format "Y-m-d H:i:s"'],
            ],
        ], $result);
    }

    public function test_that_match_rule_with_regex_pattern_extracts_the_pattern_correctly(): void
    {
        $result = RulesParser::parse([
            ['field' => 'slug', 'label' => 'Slug', 'rules' => 'match[/[a-z0-9-]+/]'],
        ]);

        self::assertSame([
            'slug' => [
                ['type' => 'Match', 'args' => ['/[a-z0-9-]+/'], 'error' => 'Slug must match the regular expression "/[a-z0-9-]+/"'],
            ],
        ], $result);
    }

    public function test_that_custom_error_message_is_passed_through_for_the_correct_rule(): void
    {
        $result = RulesParser::parse([
            [
                'field'  => 'slug',
                'label'  => 'Slug',
                'rules'  => 'required',
                'errors' => ['required' => '%s cannot be left blank'],
            ],
        ]);

        self::assertSame('Slug cannot be left blank', $result['slug'][0]['error']);
    }

    public function test_that_field_definition_without_errors_key_uses_default_error_messages(): void
    {
        $result = RulesParser::parse([
            ['field' => 'username', 'label' => 'Username', 'rules' => 'required'],
        ]);

        self::assertSame('Username is required', $result['username'][0]['error']);
    }

    public function test_that_custom_error_for_one_rule_does_not_affect_other_rules_on_the_same_field(): void
    {
        $result = RulesParser::parse([
            [
                'field'  => 'email',
                'label'  => 'Email',
                'rules'  => 'required|email',
                'errors' => ['email' => '%s must be a proper email address'],
            ],
        ]);

        self::assertSame('Email is required',              $result['email'][0]['error']);
        self::assertSame('Email must be a proper email address', $result['email'][1]['error']);
    }

    public function test_that_missing_field_key_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        RulesParser::parse([
            ['label' => 'Email', 'rules' => 'required'],
        ]);
    }

    public function test_that_missing_label_key_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        RulesParser::parse([
            ['field' => 'email', 'rules' => 'required'],
        ]);
    }

    public function test_that_missing_rules_key_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        RulesParser::parse([
            ['field' => 'email', 'label' => 'Email'],
        ]);
    }

    public function test_that_non_string_field_key_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        RulesParser::parse([
            ['field' => ['not', 'a', 'string'], 'label' => 'Email', 'rules' => 'required'],
        ]);
    }

    public function test_that_unsupported_rule_name_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        RulesParser::parse([
            ['field' => 'email', 'label' => 'Email', 'rules' => 'unsupported_rule'],
        ]);
    }
}
