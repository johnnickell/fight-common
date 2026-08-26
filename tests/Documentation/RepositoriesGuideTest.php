<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrineTransactionalUnitOfWork::class)]
final class RepositoriesGuideTest extends UnitTestCase
{
    public function test_that_new_consumers_are_directed_to_the_canonical_doctrine_transaction_boundary(): void
    {
        $root = dirname(__DIR__, 2);
        $guide = file_get_contents($root.'/docs/repositories.md');
        $readme = file_get_contents($root.'/README.md');
        $documentationIndex = file_get_contents($root.'/docs/README.md');
        $composer = file_get_contents($root.'/composer.json');
        $legacyContract = file_get_contents($root.'/src/Application/Repository/UnitOfWork.php');

        self::assertIsString($guide);
        self::assertIsString($readme);
        self::assertIsString($documentationIndex);
        self::assertIsString($composer);
        self::assertIsString($legacyContract);

        foreach ([
            'Fight\Common\Application\Repository\TransactionalUnitOfWork',
            'Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork',
            '$unitOfWork->commitTransactional(',
            '## Deprecated 1.x compatibility',
            'Fight\Common\Adapter\Repository\DoctrineUnitOfWork',
            'UnitOfWork::commit()',
        ] as $requiredContract) {
            self::assertStringContainsString($requiredContract, $guide);
        }

        self::assertStringContainsString('Adapter\\Persistence\\Doctrine\\DoctrineTransactionalUnitOfWork', $readme);
        self::assertStringContainsString('deprecated 1.x compatibility', $readme);
        self::assertStringContainsString(
            'Fight\\Common\\Adapter\\Persistence\\Doctrine\\DoctrineTransactionalUnitOfWork',
            $documentationIndex,
        );
        self::assertStringContainsString('deprecated 1.x compatibility', $documentationIndex);
        self::assertStringContainsString('canonical Doctrine transactional unit of work', $composer);
        self::assertStringContainsString(
            '@deprecated Retained for 1.x compatibility. Use TransactionalUnitOfWork for new consumers.',
            $legacyContract,
        );
    }

    public function test_that_release_guidance_classifies_the_canonical_and_legacy_journeys(): void
    {
        $root = dirname(__DIR__, 2);
        $changelog = file_get_contents($root.'/CHANGELOG.md');
        $installedProbe = file_get_contents($root.'/release/fixtures/PublicApiConsumer/public-api-probe.php');

        self::assertIsString($changelog);
        self::assertIsString($installedProbe);
        self::assertStringContainsString(
            '`Adapter\\Persistence\\Doctrine\\DoctrineTransactionalUnitOfWork`',
            $changelog,
        );
        self::assertStringContainsString(
            '`Adapter\\Repository\\DoctrineUnitOfWork` and `Application\\Repository\\UnitOfWork::commit()`',
            $changelog,
        );
        self::assertStringContainsString('Deprecated 1.x compatibility journey.', $installedProbe);
        self::assertStringContainsString('Canonical transaction-only journey.', $installedProbe);
    }
}
