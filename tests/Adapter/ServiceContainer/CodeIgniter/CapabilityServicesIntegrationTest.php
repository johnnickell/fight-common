<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Config\Services as CoreServices;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Queue\Interfaces\QueueInterface;
use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Adapter\Messaging\CodeIgniter\CommandMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\EventMessageJob;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueCommandBus;
use Fight\Common\Adapter\Messaging\CodeIgniter\QueueEventDispatcher;
use Fight\Common\Adapter\Messaging\Handler\CommandMessageHandler;
use Fight\Common\Adapter\Messaging\Handler\EventMessageHandler;
use Fight\Common\Adapter\Persistence\CodeIgniter\CodeIgniterTransactionalUnitOfWork;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\MessagingServices;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\PersistenceServices;
use Fight\Common\Application\Messaging\Command\AsynchronousCommandBus;
use Fight\Common\Application\Messaging\Command\SynchronousCommandBus;
use Fight\Common\Application\Messaging\Event\AsynchronousEventDispatcher;
use Fight\Common\Application\Messaging\Event\SynchronousEventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;

#[CoversClass(MessagingServices::class)]
#[CoversClass(PersistenceServices::class)]
final class CapabilityServicesIntegrationTest extends UnitTestCase
{
    private ?string $project = null;

    public function test_that_capability_delegates_return_their_neutral_contracts(): void
    {
        $queue = $this->mock(QueueInterface::class);
        self::assertInstanceOf(
            AsynchronousCommandBus::class,
            MessagingServices::asynchronousCommandBus($queue, 'commands', 'fight-command')
        );
        self::assertInstanceOf(
            AsynchronousEventDispatcher::class,
            MessagingServices::asynchronousEventDispatcher($queue, 'events', 'fight-event')
        );

        $connection = $this->mock(BaseConnection::class);
        $connection->shouldReceive('getConnection')->once()->andReturnFalse();
        self::assertInstanceOf(
            TransactionalUnitOfWork::class,
            PersistenceServices::transactionalUnitOfWork($connection)
        );
    }

    #[RunInSeparateProcess]
    public function test_that_a_project_owned_messaging_services_fixture_boots_without_persistence_activation(): void
    {
        $this->bootProjectServices('MessagingServices.php');
        $queue = $this->mock(QueueInterface::class);
        $synchronousCommandBus = $this->mock(SynchronousCommandBus::class);
        $synchronousEventDispatcher = $this->mock(SynchronousEventDispatcher::class);
        \Config\Services::override('fightQueueCollaborator', $queue);
        \Config\Services::override('fightSynchronousCommandBusCollaborator', $synchronousCommandBus);
        \Config\Services::override('fightSynchronousEventDispatcherCollaborator', $synchronousEventDispatcher);

        self::assertInstanceOf(QueueCommandBus::class, \Config\Services::fightQueueCommandBus());
        self::assertInstanceOf(AsynchronousCommandBus::class, \Config\Services::fightAsynchronousCommandBus());
        self::assertInstanceOf(QueueEventDispatcher::class, \Config\Services::fightQueueEventDispatcher());
        self::assertInstanceOf(
            AsynchronousEventDispatcher::class,
            \Config\Services::fightAsynchronousEventDispatcher()
        );
        self::assertInstanceOf(CommandMessageHandler::class, \Config\Services::fightCommandMessageHandler());
        self::assertInstanceOf(EventMessageHandler::class, \Config\Services::fightEventMessageHandler());
        self::assertFalse(method_exists(\Config\Services::class, 'fightTransactionalUnitOfWork'));
        self::assertFalse(\Config\Services::has('fightTransactionalUnitOfWork'));
        self::assertInstanceOf(RouteCollectionInterface::class, \Config\Services::get('routes'));
    }

    #[RunInSeparateProcess]
    public function test_that_a_project_owned_persistence_services_fixture_boots_without_messaging_activation(): void
    {
        $this->bootProjectServices('PersistenceServices.php');
        $connection = $this->mock(BaseConnection::class);
        $connection->shouldReceive('getConnection')->once()->andReturnFalse();
        \Config\Services::override('fightDatabaseConnectionCollaborator', $connection);

        self::assertInstanceOf(
            CodeIgniterTransactionalUnitOfWork::class,
            \Config\Services::fightCodeIgniterTransactionalUnitOfWork()
        );
        self::assertInstanceOf(TransactionalUnitOfWork::class, \Config\Services::fightTransactionalUnitOfWork());
        self::assertFalse(method_exists(\Config\Services::class, 'fightQueueCommandBus'));
        self::assertFalse(method_exists(\Config\Services::class, CommandMessageJob::HANDLER_SERVICE));
        self::assertFalse(\Config\Services::has('fightQueueCommandBus'));
        self::assertFalse(CoreServices::has('queue'));
        self::assertInstanceOf(RouteCollectionInterface::class, \Config\Services::get('routes'));
    }

    private function bootProjectServices(string $fixture): void
    {
        $root = dirname(__DIR__, 4);
        $this->project = sys_get_temp_dir().'/fight-common-codeigniter-'.bin2hex(random_bytes(8));
        $app = $this->project.'/app';
        $config = $app.'/Config';
        $framework = $root.'/vendor/codeigniter4/framework';
        $frameworkConfig = $framework.'/app/Config';

        if (! mkdir($config, 0700, true) && ! is_dir($config)) {
            throw new RuntimeException('Could not create the isolated CodeIgniter project configuration.');
        }

        foreach ([$this->project.'/public', $this->project.'/writable'] as $directory) {
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Could not create the isolated CodeIgniter project directory.');
            }
        }

        $this->writeProjectConfiguration($config, $frameworkConfig, $fixture);

        $_SERVER['CI_ENVIRONMENT'] = 'testing';
        define('ENVIRONMENT', 'testing');
        define('CI_DEBUG', true);
        define('HOMEPATH', $this->project.'/');
        define('CONFIGPATH', $config.'/');
        define('PUBLICPATH', $this->project.'/public/');

        require $config.'/Paths.php';
        $paths = new \Config\Paths();
        define('APPPATH', $app.'/');
        define('ROOTPATH', $this->project.'/');
        define('SYSTEMPATH', $framework.'/system/');
        define('WRITEPATH', $this->project.'/writable/');
        define('TESTPATH', $root.'/tests/');
        define('CIPATH', $framework.'/');
        define('FCPATH', $this->project.'/public/');
        define('SUPPORTPATH', $root.'/tests/_support/');
        define('COMPOSER_PATH', $root.'/vendor/autoload.php');
        define('VENDORPATH', $root.'/vendor/');

        Container::getInstance()->instance('config', new class {
            public function get(mixed $key, mixed $default = null): mixed
            {
                if (! is_string($key)) {
                    return $default;
                }

                return \CodeIgniter\Config\Factories::get('config', $key) ?? $default;
            }
        });

        require $framework.'/system/Boot.php';
        \CodeIgniter\Boot::bootTest($paths);
        CoreServices::reset(false);
        CoreServices::resetServicesCache();
        \Config\Services::reset(false);
        \Config\Services::resetServicesCache();
    }

    protected function tearDown(): void
    {
        if ($this->project !== null && is_dir($this->project)) {
            $this->removeTemporaryDirectory($this->project, 'fight-common-codeigniter-');
        }

        parent::tearDown();
    }

    private function writeProjectConfiguration(string $config, string $frameworkConfig, string $fixture): void
    {
        $paths = <<<'PHP'
<?php

declare(strict_types=1);

namespace Config;

final class Paths
{
    public string $systemDirectory = %s;
    public string $appDirectory = %s;
    public string $writableDirectory = %s;
    public string $testsDirectory = %s;
    public string $viewDirectory = %s;
    public string $envDirectory = %s;
}
PHP;
        $root = dirname($config, 2);
        $this->writeFile(
            $config.'/Paths.php',
            sprintf(
                $paths,
                var_export(dirname($frameworkConfig, 2).'/system', true),
                var_export(dirname($config), true),
                var_export($root.'/writable', true),
                var_export(dirname(__DIR__, 4).'/tests', true),
                var_export(dirname($config).'/Views', true),
                var_export($root, true),
            )
        );
        $this->writeFile(
            $config.'/Constants.php',
            "<?php\n\ndeclare(strict_types=1);\n\ndefined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');\n"
        );
        $this->writeFile(
            $config.'/Autoload.php',
            sprintf(
                "<?php\n\ndeclare(strict_types=1);\n\nnamespace Config;\n\nuse CodeIgniter\\Config\\AutoloadConfig;\n\nfinal class Autoload extends AutoloadConfig\n{\n    public \$psr4 = ['Config' => [__DIR__, %s]];\n\n    public \$helpers = [];\n}\n",
                var_export($frameworkConfig, true),
            )
        );
        $this->writeFile(
            $config.'/Modules.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Config;\n\nuse CodeIgniter\\Modules\\Modules as BaseModules;\n\nfinal class Modules extends BaseModules\n{\n    public \$enabled = true;\n\n    public \$discoverInComposer = true;\n\n    public \$composerPackages = [];\n\n    public \$aliases = ['events', 'filters', 'registrars', 'routes', 'services'];\n}\n"
        );

        $contents = file_get_contents(__DIR__.'/Fixture/'.$fixture);
        if (! is_string($contents)) {
            throw new RuntimeException('Could not read the CodeIgniter project service fixture.');
        }

        $this->writeFile($config.'/Services.php', $contents);
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not write the isolated CodeIgniter project configuration.');
        }
    }
}
