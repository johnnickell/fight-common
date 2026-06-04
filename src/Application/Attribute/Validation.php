<?php

declare(strict_types=1);

namespace Fight\Common\Application\Attribute;

use Attribute;

/**
 * Class Validation
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Validation
{
     /**
      * Constructs Validation
      *
      * @param string|null $formName
      * @param array<int, array{field: string, label: string, rules: string}> $rules
      */
    public function __construct(
        private ?string $formName = null,
        private array $rules = []
    ) {
    }

    /**
     * Retrieves the form name
     */
    public function formName(): ?string
    {
        return $this->formName;
    }

    /**
     * Retrieves the rules
     *
     * @return array<int, array{field: string, label: string, rules: string}>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
