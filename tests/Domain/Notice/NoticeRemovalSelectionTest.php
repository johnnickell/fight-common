<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Notice;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Notice\Command\RemoveNotices;
use Fight\Common\Domain\Notice\NoticeRemovalSelection;
use Fight\Test\Common\TestCase\Notice\NoticeRemovalFixture;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(NoticeRemovalSelection::class)]
#[CoversClass(RemoveNotices::class)]
final class NoticeRemovalSelectionTest extends UnitTestCase
{
    public function test_that_selection_pins_exact_incarnations_and_snapshots_caller_references(): void
    {
        $id = 'notice-1'; $position = 1; $namespace = str_repeat('a', 32);
        $target = NoticeRemovalFixture::target(); $target['id'] =& $id; $target['position'] =& $position;
        $selection = NoticeRemovalSelection::fromArray(['version' => 1, 'namespace' => &$namespace, 'targets' => [$target]]);
        $id = 'changed'; $position = 2; $namespace = str_repeat('b', 32);
        self::assertSame(str_repeat('a', 32), $selection->namespace());
        self::assertSame([NoticeRemovalFixture::target()], $selection->targets());
        $command = new RemoveNotices($selection);
        self::assertSame($selection, $command->selection());
        self::assertSame($selection->toArray(), RemoveNotices::fromArray($command->toArray())->toArray());
        $copy = $selection->targets(); $copy[0]['id'] = 'changed';
        self::assertSame('notice-1', $selection->targets()[0]['id']);
        $targets = [];
        for ($i = 1; $i <= 100; ++$i) { $targets[] = NoticeRemovalFixture::target($i, 'notice-'.$i); }
        self::assertCount(100, NoticeRemovalSelection::fromArray(['version' => 1, 'namespace' => str_repeat('0', 32), 'targets' => $targets])->targets());
    }

    #[DataProvider('invalidSelections')]
    public function test_that_invalid_unbounded_or_ambiguous_selections_reject(array $data): void
    {
        $this->expectException(DomainException::class); NoticeRemovalSelection::fromArray($data);
    }

    public static function invalidSelections(): iterable
    {
        $valid = ['version' => 1, 'namespace' => str_repeat('a', 32), 'targets' => [NoticeRemovalFixture::target()]];
        yield [[]];
        foreach (['version' => '1', 'namespace' => 'private', 'targets' => null] as $field => $value) { yield [array_replace($valid, [$field => $value])]; }
        yield [$valid + ['cutoff' => '2026-10-25']];
        foreach ([[], ['key' => NoticeRemovalFixture::target()], array_fill(0, 101, NoticeRemovalFixture::target()), ['not-an-array']] as $targets) { yield [array_replace($valid, ['targets' => $targets])]; }
        foreach ([['position' => 0], ['position' => 2147483648], ['position' => '1'], ['id' => ''], ['scope' => 'bad scope'], ['producer' => str_repeat('a', 97)], ['extra' => true]] as $change) {
            yield [array_replace($valid, ['targets' => [array_replace(NoticeRemovalFixture::target(), $change)]])];
        }
        yield [array_replace($valid, ['targets' => [NoticeRemovalFixture::target(), NoticeRemovalFixture::target(1, 'other')]])];
        yield [array_replace($valid, ['targets' => [NoticeRemovalFixture::target(), NoticeRemovalFixture::target(2)]])];
    }
}
