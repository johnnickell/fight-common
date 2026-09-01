<?php

declare(strict_types=1);

namespace Fight\Test\Release\Application;

use Fight\Release\Application\StarterSupportReceiptAuthority;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StarterSupportReceiptAuthority::class)]
/**
 * Class StarterSupportReceiptAuthorityTest
 */
final class StarterSupportReceiptAuthorityTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves a complete passing receipt and its immutable location are accepted.
     */
    public function test_that_accepts_a_complete_passing_receipt_and_immutable_pin(): void
    {
        $authority = new StarterSupportReceiptAuthority();

        self::assertTrue($authority->isValid($this->receipt()));
        self::assertTrue($authority->isValidPin($this->pin()));
    }

    /**
     * Proves malformed and non-resumable evidence fails closed.
     */
    public function test_that_rejects_missing_malformed_and_non_resumable_evidence(): void
    {
        $authority = new StarterSupportReceiptAuthority();
        $missing = $this->receipt();
        unset($missing['lock_sha256']);
        $malformed = $this->receipt();
        $malformed['candidate']['version'] = '2.0.0';
        $failed = $this->receipt();
        $failed['result'] = 'failed';
        $failed['journeys'][0]['status'] = 'failed';
        $failed['next_action'] = null;

        self::assertFalse($authority->isValid($missing));
        self::assertFalse($authority->isValid($malformed));
        self::assertFalse($authority->isValid($failed));
        self::assertFalse($authority->isValidPin(['framework' => 'symfony']));
    }

    /**
     * Proves invalid capability, journey, and composition inputs fail closed.
     */
    public function test_that_rejects_invalid_capability_journey_and_composition_inputs(): void
    {
        $authority = new StarterSupportReceiptAuthority();
        $invalidCapability = $this->receipt();
        $invalidCapability['capabilities']['container'] = 'skipped';
        $emptyCapabilities = $this->receipt();
        $emptyCapabilities['capabilities'] = [];
        $invalidJourney = $this->receipt();
        $invalidJourney['journeys'][0]['evidence'] = '';
        $emptyJourneys = $this->receipt();
        $emptyJourneys['journeys'] = [];
        $pins = [];
        $receipts = [];
        foreach (['symfony', 'laravel', 'yii', 'codeigniter', 'slim'] as $framework) {
            $pins[$framework] = $this->pin($framework);
            $receipts[$framework] = $this->receipt($framework);
        }
        $pins['slim']['path'] = 'evidence/incorrect.json';

        self::assertFalse($authority->isValid($invalidCapability));
        self::assertFalse($authority->isValid($emptyCapabilities));
        self::assertFalse($authority->isValid($invalidJourney));
        self::assertFalse($authority->isValid($emptyJourneys));
        self::assertFalse($authority->hasPassingComposition([], [], 'not-a-reference'));
        self::assertFalse($authority->hasPassingComposition($pins, $receipts, str_repeat('b', 40)));
    }

    /**
     * Proves each required framework must provide a passing matched receipt.
     */
    public function test_that_requires_all_five_passing_candidate_matched_pins(): void
    {
        $authority = new StarterSupportReceiptAuthority();
        $pins = [];
        $receipts = [];
        foreach (['symfony', 'laravel', 'yii', 'codeigniter', 'slim'] as $framework) {
            $pins[$framework] = $this->pin($framework);
            $receipts[$framework] = $this->receipt($framework);
        }

        self::assertTrue($authority->hasPassingComposition($pins, $receipts, str_repeat('b', 40)));

        $receipts['yii']['result'] = 'unavailable';
        $receipts['yii']['journeys'][0]['status'] = 'unavailable';
        $receipts['yii']['next_action'] = ['action' => 'install_stable_queue_and_retry'];

        self::assertFalse($authority->hasPassingComposition($pins, $receipts, str_repeat('b', 40)));
    }

    /**
     * Returns one valid passing starter receipt.
     *
     * @return array<string, mixed>
     */
    private function receipt(string $framework = 'symfony'): array
    {
        return [
            'schema_version' => StarterSupportReceiptAuthority::SCHEMA_VERSION,
            'content_id'     => str_repeat('a', 64),
            'candidate'      => [
                'package'   => 'johnnickell/fight-common',
                'version'   => '1.2.0-dev',
                'reference' => str_repeat('b', 40)
            ],
            'framework'      => [
                'name'      => $framework,
                'version'   => 'current',
                'providers' => ['framework/provider']
            ],
            'lock_sha256'    => str_repeat('c', 64),
            'capabilities'   => ['container' => 'ship', 'queue' => 'wire'],
            'journeys'       => [[
                'name'     => 'booted_lifecycle',
                'status'   => 'passed',
                'evidence' => 'var/evidence/journey.log'
            ]],
            'result'         => 'passed',
            'evidence'       => [
                'build'          => './bin/build',
                'planning_check' => './bin/planning-check',
                'receipt_sha256' => str_repeat('d', 64)
            ],
            'next_action'    => null
        ];
    }

    /**
     * Returns immutable coordinates for one canonical starter receipt.
     *
     * @return array<string, string>
     */
    private function pin(string $framework = 'symfony'): array
    {
        return [
            'framework'  => $framework,
            'repository' => 'https://github.com/johnnickell/project-'.$framework,
            'commit'     => str_repeat('e', 40),
            'path'       => 'evidence/framework-support/receipt-v1.json',
            'sha256'     => str_repeat('f', 64)
        ];
    }
}
    /**
     * Proves malformed and non-resumable evidence fails closed.
     */
    /**
     * Proves each required framework must provide a passing matched receipt.
     */
