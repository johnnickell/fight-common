<?php

class MissingType
{
    public function missingMethod(): void
    {
    }
}

/**
 *
 * Legacy interface prose
 *
 */
interface WrongInterface
{
}

/**
 * Trait UsefulTrait.
 */
trait UsefulTrait
{
}

/**
 * Enum Mode.
 */
enum Mode
{
    case Active;
}

/**
 * Historical exception prose.
 *
 * This detail must survive.
 */
class BrokenException extends RuntimeException
{
}

/**
 * Class CrampedType
 * Useful type detail.
 */
class CrampedType
{
}

/**
 */
class EmptyType
{
}

/**
 * @template T
 */
class AnnotationFirstType
{
}

/** One-line type prose. */
class OneLineType
{
}

#[Attribute]
final readonly class AttributedMissingType
{
}

/**
 * Class DocumentedService
 */
class DocumentedService
{
    /**
     * @param string $name A useful parameter annotation
     */
    public function __construct(private string $name)
    {
    }

    /**
     * Get the name.
     *
     * This useful explanation must survive.
     *
     * @return string The current name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the current name.
     */
    public function punctuated(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the current name
     * @return string The current name
     */
    public function cramped(): string
    {
        return $this->name;
    }

    /**
     * Return the current name
     */
    public function mappedImperative(): string
    {
        return $this->name;
    }

    /**
     * Understands the current name
     */
    public function ambiguous(): string
    {
        return $this->name;
    }

    /**
     * Retrieves the current
     * name
     */
    public function wrapped(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     * @return string The current name
     */
    public function inheritDocWithContent(): string
    {
        return $this->name;
    }

    /**
     * @return string The current name
     */
    public function annotationFirst(): string
    {
        return $this->name;
    }

    /** Compute the name length */
    public function computeLength(): int
    {
        return strlen($this->name);
    }

    /** Summarize the name */
    public function summarize(): string
    {
        return $this->name;
    }

    /** Unload the name */
    public function unload(): string
    {
        return $this->name;
    }
}

/**
 * Class MissingConstructorService
 */
class MissingConstructorService
{
    #[Attribute]
    public function __construct()
    {
    }
}

/**
 * Class WrongConstructorService
 */
class WrongConstructorService
{
    /**
     * Legacy construction prose
     */
    public function __construct()
    {
    }
}

/**
 * Class PunctuatedConstructorService
 */
class PunctuatedConstructorService
{
    /**
     * Constructs PunctuatedConstructorService.
     */
    public function __construct()
    {
    }

    /**
     */
    public function emptySummary(): void
    {
    }
}
