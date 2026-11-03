<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Validation\Internal;

use Fight\Common\Application\Validation\Data\InputData;
use Fight\Common\Application\Validation\Internal\ValidatorFactory;
use Fight\Common\Application\Validation\ValidationContext;
use Fight\Test\Common\Fixture\ValidationRuleExamples;
use Fight\Test\Common\TestCase\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ValidatorFactory::class)]
class ValidatorFactoryTest extends UnitTestCase
{
    #[DataProvider('ruleExamples')]
    public function test_that_constructed_validators_apply_rules_and_preserve_error_data(
        string $type,
        array $args,
        array $passing,
        array $failing
    ): void {
        $validator = ValidatorFactory::create($type, 'value', 'Declared error', $args);
        $context = new ValidationContext(new InputData($passing));

        self::assertTrue($validator->validate($context));
        self::assertSame([], $context->getErrors());

        $context = new ValidationContext(new InputData($failing));
        self::assertFalse($validator->validate($context));
        self::assertSame(['value' => ['Declared error']], $context->getErrors());
    }

    public function test_that_unknown_internal_registration_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported validation registration: Unknown');

        ValidatorFactory::create('Unknown', 'value', 'Declared error');
    }

    public static function ruleExamples(): iterable
    {
        foreach (ValidationRuleExamples::cases() as $name => [$type, , $args, $passing, $failing]) {
            yield $name => [$type, $args, $passing, $failing];
        }
    }
}
