<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Execution\Session\Service;

use Fight\Common\Application\Execution\Session\Service\ExecutionAccess;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExecutionAccess::class)]
final class ExecutionAccessTest extends UnitTestCase
{
    public function test_that_access_copies_explicit_session_grants_and_preserves_empty_whole_and_numeric_projects(): void
    {
        $session = 'a';
        $grants = ExecutionAccess::create(['project' => [&$session, 'a'], 'empty' => [], 'all' => null, '123' => ['456']]);
        $session = 'changed';
        self::assertSame(['project' => ['a'], 'empty' => [], 'all' => null, 123 => ['456']], $grants->projects());
    }

    #[DataProvider('invalidAccess')]
    public function test_that_trusted_access_still_requires_finite_valid_scope(array $access): void
    {
        $this->expectException(DomainException::class);
        ExecutionAccess::create($access);
    }

    public static function invalidAccess(): iterable
    {
        yield [array_fill_keys(range('a', 'q'), null)];
        yield [['invalid project' => null]];
        yield [['project' => 'session']];
        yield [['project' => ['key' => 'session']]];
        yield [['project' => array_fill(0, 101, 'session')]];
        yield [['project' => ['invalid session']]];
    }
}
