<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Specification;

use Fight\Common\Domain\Specification\AndSpecification;
use Fight\Common\Domain\Specification\CompositeSpecification;
use Fight\Common\Domain\Specification\NotSpecification;
use Fight\Common\Domain\Specification\OrSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompositeSpecification::class)]
class CompositeSpecificationTest extends UnitTestCase
{
    private function always(bool $value): CompositeSpecification
    {
        return new class($value) extends CompositeSpecification {
            public function __construct(private readonly bool $value) {}
            public function isSatisfiedBy(mixed $candidate): bool { return $this->value; }
        };
    }

    public function test_that_and_returns_an_and_specification(): void
    {
        $spec = $this->always(true)->and($this->always(true));

        self::assertInstanceOf(AndSpecification::class, $spec);
    }

    public function test_that_or_returns_an_or_specification(): void
    {
        $spec = $this->always(true)->or($this->always(false));

        self::assertInstanceOf(OrSpecification::class, $spec);
    }

    public function test_that_not_returns_a_not_specification(): void
    {
        $spec = $this->always(true)->not();

        self::assertInstanceOf(NotSpecification::class, $spec);
    }

    public function test_that_and_composition_preserves_boolean_and_semantics(): void
    {
        $candidate = new \stdClass();

        self::assertTrue($this->always(true)->and($this->always(true))->isSatisfiedBy($candidate));
        self::assertFalse($this->always(true)->and($this->always(false))->isSatisfiedBy($candidate));
        self::assertFalse($this->always(false)->and($this->always(true))->isSatisfiedBy($candidate));
        self::assertFalse($this->always(false)->and($this->always(false))->isSatisfiedBy($candidate));
    }

    public function test_that_or_composition_preserves_boolean_or_semantics(): void
    {
        $candidate = new \stdClass();

        self::assertTrue($this->always(true)->or($this->always(true))->isSatisfiedBy($candidate));
        self::assertTrue($this->always(true)->or($this->always(false))->isSatisfiedBy($candidate));
        self::assertTrue($this->always(false)->or($this->always(true))->isSatisfiedBy($candidate));
        self::assertFalse($this->always(false)->or($this->always(false))->isSatisfiedBy($candidate));
    }

    public function test_that_not_composition_preserves_boolean_negation_semantics(): void
    {
        $candidate = new \stdClass();

        self::assertFalse($this->always(true)->not()->isSatisfiedBy($candidate));
        self::assertTrue($this->always(false)->not()->isSatisfiedBy($candidate));
    }

    public function test_that_chained_and_or_composition_evaluates_correctly(): void
    {
        $candidate = new \stdClass();

        // (true AND false) OR true → true
        $spec = $this->always(true)->and($this->always(false))->or($this->always(true));
        self::assertTrue($spec->isSatisfiedBy($candidate));

        // (true AND false) OR false → false
        $spec = $this->always(true)->and($this->always(false))->or($this->always(false));
        self::assertFalse($spec->isSatisfiedBy($candidate));
    }

    public function test_that_chained_not_and_composition_evaluates_correctly(): void
    {
        $candidate = new \stdClass();

        // NOT(true) AND true → false
        $spec = $this->always(true)->not()->and($this->always(true));
        self::assertFalse($spec->isSatisfiedBy($candidate));

        // NOT(false) AND true → true
        $spec = $this->always(false)->not()->and($this->always(true));
        self::assertTrue($spec->isSatisfiedBy($candidate));
    }

    public function test_that_double_negation_evaluates_correctly(): void
    {
        $candidate = new \stdClass();

        self::assertTrue($this->always(true)->not()->not()->isSatisfiedBy($candidate));
        self::assertFalse($this->always(false)->not()->not()->isSatisfiedBy($candidate));
    }
}
