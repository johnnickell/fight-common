<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Task;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Task\Command\RemoveTasks;
use Fight\Common\Domain\Task\Event\TaskDataRemoved;
use Fight\Common\Domain\Task\TaskSelection;
use Fight\Test\Common\TestCase\Task\TaskFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TaskSelection::class)]
#[CoversClass(RemoveTasks::class)]
#[CoversClass(TaskDataRemoved::class)]
final class TaskSelectionTest extends UnitTestCase
{
    public function test_that_exact_bounded_selection_and_reference_only_messages_round_trip(): void
    {
        $ids = array_map(static fn (int $id): string => str_pad(dechex($id), 32, '0', STR_PAD_LEFT), range(1, 100));
        $selection = TaskSelection::fromIds(str_repeat('s', 96), $ids);
        self::assertSame($ids, $selection->ids());
        self::assertSame(str_repeat('s', 96), $selection->scope());
        $command = new RemoveTasks($selection);
        self::assertSame($selection, $command->selection());
        self::assertSame(['scope' => str_repeat('s', 96), 'ids' => $ids], $command->toArray());
        self::assertSame($command->toArray(), RemoveTasks::fromArray($command->toArray())->toArray());
        $event = new TaskDataRemoved($selection);
        self::assertSame($command->toArray(), $event->toArray());
        self::assertSame($event->toArray(), TaskDataRemoved::fromArray($event->toArray())->toArray());
    }

    #[DataProvider('invalidSelections')]
    public function test_that_invalid_overbound_and_duplicate_selections_reject(string $scope, array $ids): void
    {
        $this->expectException(DomainException::class);
        TaskSelection::fromIds($scope, $ids);
    }

    public static function invalidSelections(): iterable
    {
        yield ['', [TaskFixture::ID]];
        yield [str_repeat('s', 97), [TaskFixture::ID]];
        yield ['scope', []];
        yield ['scope', array_fill(0, 101, TaskFixture::ID)];
        yield ['scope', [TaskFixture::ID, TaskFixture::ID]];
        yield ['scope', [1 => TaskFixture::ID]];
        yield ['scope', [123]];
        yield ['scope', [null]];
        yield ['scope', ['']];
        yield ['scope', [str_repeat('a', 33)]];
        yield ['scope', [str_repeat('A', 32)]];
    }
}
