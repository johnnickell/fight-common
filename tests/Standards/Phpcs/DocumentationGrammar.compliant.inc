<?php

function freeFunction(): void
{
}

/**
 * Interface NamedContract
 */
interface NamedContract
{
    /**
     * Retrieves the value
     */
    public function value(): string;
}

/**
 * Class CompliantService
 */
#[Attribute]
class CompliantService implements NamedContract
{
    /**
     * Constructs CompliantService
     */
    public function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public function value(): string
    {
        return 'value';
    }

    /**
     * Creates a closure
     */
    public function closure(): Closure
    {
        return function (): void {
        };
    }

    /**
     * Creates a nested anonymous object
     */
    public function anonymousObject(): object
    {
        return new class () {
            public function undocumented(): void
            {
            }
        };
    }

    /**
     * Declares a nested function conditionally
     */
    public function nestedFunction(): void
    {
        if (!function_exists('nestedDocumentationFixture')) {
            function nestedDocumentationFixture(): void
            {
            }
        }
    }
}

$anonymous = new class () {
    public function undocumented(): void
    {
    }
};

class ExcludedExampleTest
{
    public function undocumented(): void
    {
    }
}
