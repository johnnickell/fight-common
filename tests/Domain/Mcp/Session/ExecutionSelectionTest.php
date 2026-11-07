<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Mcp\Session;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Mcp\Session\ExecutionSelection;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ExecutionSelection::class)]
final class ExecutionSelectionTest extends UnitTestCase
{
    public function test_that_selection_copies_filters_and_normalizes_projects_without_granting_access(): void
    {
        $projects = ['z', 'a', 'a'];
        $value = 'session';
        $selection = ExecutionSelection::create($projects, ['session_id' => &$value]);
        $value = 'changed';
        $projects[0] = 'changed';
        self::assertSame(['a', 'z'], $selection->projects());
        self::assertSame(['session_id' => 'session'], $selection->filters());
        self::assertSame(['projects' => ['a', 'z'], 'filters' => ['session_id' => 'session']], $selection->toArray());
        self::assertSame($selection->toArray(), ExecutionSelection::fromArray($selection->toArray())->toArray());
    }

    #[DataProvider('invalidSelections')]
    public function test_that_selection_rejects_unsupported_filters_and_unbounded_or_malformed_projects(array $projects, array $filters): void
    {
        $this->expectException(DomainException::class);
        ExecutionSelection::create($projects, $filters);
    }

    public static function invalidSelections(): iterable
    {
        yield [['key' => 'project'], []];
        yield [array_fill(0, 17, 'project'), []];
        yield [['unsafe body'], []];
        yield [['project'], ['payload' => 'secret']];
        yield [['project'], ['session_id' => [], 'consumer_reference' => 'x']];
        yield [['project'], ['a' => 1, 'b' => 2, 'c' => 3]];
    }

    #[DataProvider('invalidPayloads')]
    public function test_that_selection_rejects_noncanonical_shapes(array $data): void
    {
        $this->expectException(DomainException::class);
        ExecutionSelection::fromArray($data);
    }

    public static function invalidPayloads(): iterable
    {
        yield [['projects' => [], 'filters' => [], 'access' => []]];
        yield [['projects' => 'project', 'filters' => []]];
    }
}
