<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs\Fixture;

trait SharedBehavior
{
    public function sharedBehavior(): void
    {
    }
}

enum State: string
{
    use SharedBehavior;

    case READY = 'ready';
    case COMPLETE = 'complete';

    public const string PUBLIC_LABEL = 'state';

    protected const string PROTECTED_LABEL = 'protected';

    private const string PRIVATE_LABEL = 'private';

    public static function default(): self
    {
        return self::READY;
    }

    public function label(): string
    {
        return $this->value;
    }

    private function privateLabel(): string
    {
        return self::PRIVATE_LABEL;
    }

    public function __invoke(): string
    {
        return $this->privateLabel();
    }
}

abstract class ComprehensiveLayoutSubject
{
    use SharedBehavior {
        sharedBehavior as private aliasedBehavior;
    }

    const string IMPLICIT_PUBLIC = 'implicit';
    public const string FIRST_PUBLIC = 'first';
    public const string SECOND_PUBLIC = 'second';

    protected const string PROTECTED_VALUE = 'protected';

    private const string PRIVATE_VALUE = 'private';

    /**
     * Public shared state.
     */
    public static string $publicShared;
    /* Public instance state. */
    public string $publicState;

    protected static string $protectedShared;
    protected string $protectedState;

    private static string $privateShared;
    private string $privateState;

    public function __construct()
    {
    }

    final public static function create(): self
    {
        throw new \LogicException();
    }

    abstract public static function createAbstract(): self;

    public static function publicShared(): string
    {
        return self::$publicShared;
    }

    final public function finalPublic(): void
    {
    }

    abstract public function abstractPublic(): void;

    public function publicBehavior(): void
    {
    }

    final protected static function finalProtectedShared(): void
    {
    }

    abstract protected static function abstractProtectedShared(): void;

    protected static function protectedSharedBehavior(): void
    {
    }

    final protected function finalProtected(): void
    {
    }

    abstract protected function abstractProtected(): void;

    protected function protectedBehavior(): void
    {
    }

    private static function privateSharedBehavior(): void
    {
    }

    private function privateBehavior(): void
    {
    }

    public function __destruct()
    {
    }

    public function __invoke(): void
    {
    }
}
