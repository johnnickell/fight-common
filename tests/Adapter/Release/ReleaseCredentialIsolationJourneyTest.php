<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Release;

use Fight\Common\Adapter\Release\Fake\DeterministicReleaseBoundaryFake;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ReleaseCredentialIsolationJourneyTest
 *
 * Covers credential isolation across the public journey and deterministic fake.
 */
#[CoversClass(DeterministicReleaseBoundaryFake::class)]
final class ReleaseCredentialIsolationJourneyTest extends UnitTestCase
{
    private const int OLD_ACCESS_TIME = 946684800;
    private const int OLD_MODIFICATION_TIME = 946684801;

    /** @var list<string> */
    private const array CREDENTIAL_BAITS = [
        'synthetic-github-token-never-use',
        'synthetic-packagist-token-never-use',
        'synthetic-signing-key-never-use',
        'synthetic-signing-passphrase-never-use',
        'synthetic-git-password-never-use'
    ];

    private string $credentialRoot;
    private string $outputRoot;
    private string $accessMarker;

    /** @var array<string, string> */
    private array $credentialFiles = [];

    /** @var array<string, string> */
    private array $originalDigests = [];

    /**
     * Creates isolated synthetic credential paths whose access is observable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->credentialRoot = sys_get_temp_dir().'/fight-common-release-credentials-'.$suffix;
        $this->outputRoot = dirname(__DIR__, 3).'/.runs/release-credential-isolation-'.$suffix;
        $this->accessMarker = $this->credentialRoot.'/credential-helper-invoked';

        foreach (['home', 'composer', 'gnupg/private-keys-v1.d', 'gh'] as $directory) {
            mkdir($this->credentialRoot.'/'.$directory, 0700, true);
        }

        mkdir($this->outputRoot, 0777, true);

        $this->credentialFiles = [
            'GIT_CONFIG_GLOBAL'  => $this->credentialRoot.'/home/.gitconfig',
            'COMPOSER_AUTH_FILE' => $this->credentialRoot.'/composer/auth.json',
            'SIGNING_KEY_FILE'   => $this->credentialRoot.'/gnupg/private-keys-v1.d/private.key',
            'GH_HOSTS_FILE'      => $this->credentialRoot.'/gh/hosts.yml',
            'SSH_AUTH_SOCK'      => $this->credentialRoot.'/ssh-agent.sock',
            'GIT_ASKPASS'        => $this->credentialRoot.'/git-askpass'
        ];

        $contents = [
            'GIT_CONFIG_GLOBAL'  => "[credential]\n\thelper = synthetic-git-password-never-use\n",
            'COMPOSER_AUTH_FILE' => '{"github-oauth":{"github.com":"synthetic-github-token-never-use"}}',
            'SIGNING_KEY_FILE'   => 'synthetic-signing-key-never-use',
            'GH_HOSTS_FILE'      => "github.com:\n  oauth_token: synthetic-github-token-never-use\n",
            'SSH_AUTH_SOCK'      => 'synthetic-git-password-never-use',
            'GIT_ASKPASS'        => implode("\n", [
                '#!/usr/bin/env bash',
                'touch '.escapeshellarg($this->accessMarker),
                "printf '%s\\n' 'synthetic-git-password-never-use'",
                ''
            ])
        ];

        foreach ($this->credentialFiles as $name => $path) {
            file_put_contents($path, $contents[$name]);
            $this->originalDigests[$path] = (string) hash_file('sha256', $path);
            touch($path, self::OLD_MODIFICATION_TIME, self::OLD_ACCESS_TIME);
        }

        chmod($this->credentialFiles['GIT_ASKPASS'], 0700);
    }

    /**
     * Removes only this test's isolated fixtures
     */
    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory($this->credentialRoot, 'fight-common-release-credentials-');
        $this->removeTestDirectory(
            $this->outputRoot,
            dirname(__DIR__, 3).'/.runs',
            'release-credential-isolation-'
        );

        parent::tearDown();
    }

    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Proves synthetic credentials remain unavailable to public journeys and every provider fake.
     */
    public function test_that_release_journeys_and_provider_fakes_never_consult_or_expose_production_credentials(): void
    {
        $root = dirname(__DIR__, 3);
        $environment = $this->credentialEnvironment();
        $observations = [];
        $childEnvironment = $this->observeCanonicalChildEnvironment($root, $environment);

        self::assertSame([
            'HOME'                              => '/tmp/fight-common-release/home',
            'COMPOSER_HOME'                     => '/tmp/fight-common-release/composer',
            'GNUPGHOME'                         => '/tmp/fight-common-release/gnupg',
            'GH_CONFIG_DIR'                     => '/tmp/fight-common-release/gh',
            'GIT_CONFIG_GLOBAL'                 => '/dev/null',
            'GIT_CONFIG_NOSYSTEM'               => '1',
            'LANG'                              => 'C.UTF-8',
            'LC_ALL'                            => 'C.UTF-8',
            'PATH'                              => '/usr/local/bin:/usr/bin:/bin',
            'FIGHT_COMMON_RELEASE_TEST_RUNTIME' => 'fight-common-release-direct-test-v1'
        ], $childEnvironment);

        foreach (
            [
                ['inspect', '--fixture='.$root.'/tests/Fixture/Release/inspect-candidate.json'],
                [
                    'plan',
                    '--fixture='.$root.'/tests/Fixture/Release/plan-candidate.json',
                    '--output='.$this->outputRoot
                ]
            ] as $arguments
        ) {
            $process = ReleaseProcess::create([$root.'/bin/release', ...$arguments]);
            $process->setEnv($environment);
            $process->mustRun();
            $observations[] = $process->getOutput();
            $observations[] = $process->getErrorOutput();
        }

        $originalEnvironment = [];

        foreach ($environment as $name => $value) {
            $originalEnvironment[$name] = getenv($name);
            putenv($name.'='.$value);
        }

        try {
            $fake = new DeterministicReleaseBoundaryFake();
            $observations[] = json_encode([
                'git'           => $fake->inspectRepository()->value,
                'resolved_tag'  => $fake->resolveBaselineTag('v1.2.3', str_repeat('c', 40))->tagName,
                'digest'        => $fake->sha256('credential-isolation-neutral-input')->value,
                'signing'       => $fake->verify()->value,
                'authorization' => $fake->check()->value,
                'github'        => $fake->release()->value,
                'packagist'     => $fake->publish()->value,
                'effects'       => $fake->effects()
            ], JSON_THROW_ON_ERROR);
        } finally {
            foreach ($originalEnvironment as $name => $value) {
                $value === false ? putenv($name) : putenv($name.'='.$value);
            }
        }

        foreach (glob($this->outputRoot.'/*') ?: [] as $artifact) {
            $observations[] = (string) file_get_contents($artifact);
        }

        $serializedObservations = implode("\n", $observations);

        foreach (self::CREDENTIAL_BAITS as $bait) {
            self::assertStringNotContainsString($bait, $serializedObservations);
            self::assertStringNotContainsString(hash('sha256', $bait), $serializedObservations);
        }

        self::assertFileDoesNotExist($this->accessMarker);

        foreach ($this->credentialFiles as $path) {
            clearstatcache(true, $path);
            self::assertSame(self::OLD_ACCESS_TIME, fileatime($path), $path);
            self::assertSame(self::OLD_MODIFICATION_TIME, filemtime($path), $path);
            self::assertSame($this->originalDigests[$path], hash_file('sha256', $path), $path);
        }
    }

    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Returns only known synthetic credential values and isolated config paths.
     *
     * @return array<string, string>
     */
    private function credentialEnvironment(): array
    {
        return [
            'HOME'              => $this->credentialRoot.'/home',
            'COMPOSER_HOME'     => $this->credentialRoot.'/composer',
            'GNUPGHOME'         => $this->credentialRoot.'/gnupg',
            'GH_CONFIG_DIR'     => $this->credentialRoot.'/gh',
            'GIT_CONFIG_GLOBAL' => $this->credentialFiles['GIT_CONFIG_GLOBAL'],
            'GIT_ASKPASS'       => $this->credentialFiles['GIT_ASKPASS'],
            'SSH_ASKPASS'       => $this->credentialFiles['GIT_ASKPASS'],
            'SSH_AUTH_SOCK'     => $this->credentialFiles['SSH_AUTH_SOCK'],
            'SIGNING_KEY_FILE'  => $this->credentialFiles['SIGNING_KEY_FILE'],
            'GH_TOKEN'          => self::CREDENTIAL_BAITS[0],
            'GITHUB_TOKEN'      => self::CREDENTIAL_BAITS[0],
            'COMPOSER_AUTH'     => self::CREDENTIAL_BAITS[1],
            'PACKAGIST_TOKEN'   => self::CREDENTIAL_BAITS[1],
            'GPG_PRIVATE_KEY'   => self::CREDENTIAL_BAITS[2],
            'GPG_PASSPHRASE'    => self::CREDENTIAL_BAITS[3],
            'GIT_PASSWORD'      => self::CREDENTIAL_BAITS[4]
        ];
    }

    /**
     * Observes the canonical fast-path PHP process while it is blocked on a controlled FIFO.
     *
     * @param string                $root        Canonical repository root.
     * @param array<string, string> $environment Synthetic ambient environment.
     *
     * @return array<string, string>
     */
    private function observeCanonicalChildEnvironment(string $root, array $environment): array
    {
        $fifo = $this->credentialRoot.'/environment-probe.fifo';
        ReleaseProcess::create(['mkfifo', $fifo])->mustRun();

        $process = ReleaseProcess::create([
            $root.'/bin/release',
            'inspect',
            '--fixture='.$fifo
        ]);
        $process->setEnv([
            ...$environment,
            'DOCKER_BIN'        => $this->credentialRoot.'/docker-must-not-run',
            'UNRELATED_AMBIENT' => 'synthetic-unrelated-value'
        ]);
        $process->start();

        $processId = $process->getPid();
        self::assertNotNull($processId);
        $commandLine = '';
        $observedProcessId = $processId;

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidates = [$processId];
            $children = trim((string) @file_get_contents(
                '/proc/'.$processId.'/task/'.$processId.'/children'
            ));

            if ($children !== '') {
                $candidates = [...$candidates, ...array_map('intval', preg_split('/\s+/', $children) ?: [])];
            }

            foreach ($candidates as $candidateProcessId) {
                $candidateCommandLine = str_replace(
                    "\0",
                    ' ',
                    (string) @file_get_contents('/proc/'.$candidateProcessId.'/cmdline')
                );
                $candidateExecutable = @readlink('/proc/'.$candidateProcessId.'/exe');

                if (
                    !is_string($candidateExecutable)
                    || realpath($candidateExecutable) !== realpath(PHP_BINARY)
                    || !str_contains($candidateCommandLine, '/scripts/release.php')
                ) {
                    continue;
                }

                $observedProcessId = $candidateProcessId;
                $commandLine = $candidateCommandLine;
                break 2;
            }

            usleep(10_000);
        }

        self::assertStringContainsString('/scripts/release.php', $commandLine);
        $entries = explode(
            "\0",
            rtrim((string) file_get_contents('/proc/'.$observedProcessId.'/environ'), "\0")
        );
        $observed = [];

        foreach ($entries as $entry) {
            [$name, $value] = explode('=', $entry, 2);
            $observed[$name] = $value;
        }

        file_put_contents(
            $fifo,
            file_get_contents($root.'/tests/Fixture/Release/inspect-candidate.json')
        );
        $process->wait();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return $observed;
    }
}
