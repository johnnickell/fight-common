<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Prompt;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Type\Arrayable;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpPromptInfo
 */
final readonly class McpPromptInfo implements Arrayable
{
    /**
     * Constructs McpPromptInfo
     */
    private function __construct(private StrictJson $definition)
    {
    }

    /**
     * Creates bounded immutable metadata with explicit string arguments
     *
     * The supported optional fields are title, description and arguments. Unknown fields reject.
     * Names use 1–128 ASCII letters, digits, dots, underscores or hyphens, starting with a letter.
     *
     * @param array<string, mixed> $definition
     */
    public static function fromArray(array $definition): self
    {
        $data = StrictJson::fromObject($definition, maxDepth: 8);
        if (
            array_diff(array_keys($definition), ['name', 'title', 'description', 'arguments']) !== []
            || !self::validName($data->get('name'))
            || !self::validText($data)
        ) {
            throw new DomainException('Invalid Prompt definition.');
        }

        $arguments = $data->has('arguments') ? $data->get('arguments') : [];
        if (!is_array($arguments) || count($arguments) > 32) {
            throw new DomainException('Prompt arguments require a bounded list.');
        }

        $seen = [];
        foreach ($arguments as $argument) {
            if (
                !$argument instanceof StrictJson
                || array_diff(array_keys($argument->properties()), ['name', 'title', 'description', 'required']) !== []
                || !self::validName($argument->get('name'))
                || !self::validText($argument)
                || ($argument->has('required') && !is_bool($argument->get('required')))
                || isset($seen[$argument->get('name')])
            ) {
                throw new DomainException('Invalid or duplicate Prompt argument declaration.');
            }

            $seen[$argument->get('name')] = true;
        }

        if (strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 16384) {
            throw new DomainException('Prompt definition exceeds its encoded byte budget.');
        }

        return new self($data);
    }

    /**
     * Returns the exact canonical registration name
     */
    public function name(): string
    {
        return $this->definition->get('name');
    }

    /**
     * Returns whether supplied string arguments satisfy declarations and finite byte limits
     */
    public function accepts(StrictJson $arguments): bool
    {
        if (
            !$arguments->isObject() || count($arguments->properties()) > 32
            || strlen(json_encode($arguments, JSON_THROW_ON_ERROR)) > 65536
        ) {
            return false;
        }

        $remaining = $arguments->properties();
        foreach ($this->definition->get('arguments') ?? [] as $declaration) {
            $name = $declaration->get('name');
            if (!$arguments->has($name)) {
                if ($declaration->get('required') === true) {
                    return false;
                }

                continue;
            }

            $value = $arguments->get($name);
            if (!is_string($value) || strlen($value) > 16384) {
                return false;
            }

            unset($remaining[$name]);
        }

        return $remaining === [];
    }

    /**
     * Returns complete immutable discovery metadata
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition->properties();
    }

    /**
     * Returns whether an identity follows the bounded registration grammar
     */
    private static function validName(mixed $name): bool
    {
        return is_string($name) && preg_match('/\A[A-Za-z][A-Za-z0-9_.-]{0,127}\z/D', $name) === 1;
    }

    /**
     * Returns whether supported optional descriptive fields remain strings
     */
    private static function validText(StrictJson $data): bool
    {
        return (!$data->has('title') || is_string($data->get('title')))
            && (!$data->has('description') || is_string($data->get('description')));
    }
}
