<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Attribute;

use Fight\Common\Application\Attribute\Validation;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Validation::class)]
class ValidationTest extends UnitTestCase
{
    public function test_that_construction_with_defaults_yields_null_form_name_and_empty_rules(): void
    {
        $attribute = new Validation();

        self::assertNull($attribute->formName());
        self::assertSame([], $attribute->rules());
    }

    public function test_that_rules_returns_the_rules_array_passed_to_constructor(): void
    {
        $rules = ['name' => ['required', 'string'], 'email' => ['required', 'email']];

        $attribute = new Validation(rules: $rules);

        self::assertSame($rules, $attribute->rules());
    }

    public function test_that_form_name_returns_the_form_name_passed_to_constructor(): void
    {
        $attribute = new Validation(formName: 'registration');

        self::assertSame('registration', $attribute->formName());
    }

    public function test_that_construction_with_both_arguments_stores_both(): void
    {
        $rules = ['field' => ['required']];

        $attribute = new Validation(formName: 'login', rules: $rules);

        self::assertSame('login', $attribute->formName());
        self::assertSame($rules, $attribute->rules());
    }
}
