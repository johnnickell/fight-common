<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Process;

use Fight\Common\Application\Process\Process;
use Fight\Common\Application\Process\ProcessBuilder;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\MethodCallException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProcessBuilder::class)]
class ProcessBuilderTest extends UnitTestCase
{
    public function test_that_constructor_with_no_arguments_creates_empty_builder(): void
    {
        $builder = new ProcessBuilder();
        $builder->arg('echo');
        $process = $builder->getProcess();

        self::assertInstanceOf(Process::class, $process);
    }

    public function test_that_constructor_with_string_argument_adds_it(): void
    {
        $process = (new ProcessBuilder('echo'))->getProcess();

        self::assertStringContainsString('echo', $process->command());
    }

    public function test_that_constructor_with_array_arguments_adds_all(): void
    {
        $process = (new ProcessBuilder(['echo', 'hello']))->getProcess();

        self::assertStringContainsString('echo', $process->command());
        self::assertStringContainsString('hello', $process->command());
    }

    public function test_that_create_returns_new_instance(): void
    {
        $builder = ProcessBuilder::create('echo');

        self::assertInstanceOf(ProcessBuilder::class, $builder);
        self::assertInstanceOf(Process::class, $builder->getProcess());
    }

    public function test_that_prefix_with_array_is_prepended_to_command(): void
    {
        $process = ProcessBuilder::create()
            ->prefix(['php', 'vendor/bin/phpunit'])
            ->getProcess();

        self::assertStringContainsString('php', $process->command());
        self::assertStringContainsString('phpunit', $process->command());
    }

    public function test_that_prefix_with_string_is_accepted(): void
    {
        $process = ProcessBuilder::create()
            ->prefix('php')
            ->arg('test.php')
            ->getProcess();

        self::assertStringContainsString('php', $process->command());
        self::assertStringContainsString('test.php', $process->command());
    }

    public function test_that_arg_appends_argument_to_command(): void
    {
        $process = ProcessBuilder::create('echo')
            ->arg('hello')
            ->getProcess();

        self::assertStringContainsString('hello', $process->command());
    }

    public function test_that_arg_ignores_empty_string(): void
    {
        $builder = ProcessBuilder::create('echo');
        $result = $builder->arg('');

        self::assertSame($builder, $result);
        // Command should contain only 'echo', not an empty quoted string
        self::assertStringNotContainsString("''", $builder->getProcess()->command());
    }

    public function test_that_option_adds_double_dash_when_absent(): void
    {
        $process = ProcessBuilder::create('phpunit')
            ->option('filter')
            ->getProcess();

        self::assertStringContainsString('--filter', $process->command());
    }

    public function test_that_option_preserves_existing_dash_prefix(): void
    {
        $process = ProcessBuilder::create('phpunit')
            ->option('--filter')
            ->getProcess();

        self::assertStringContainsString('--filter', $process->command());
    }

    public function test_that_option_with_value_appends_value(): void
    {
        $process = ProcessBuilder::create('phpunit')
            ->option('filter', 'test_foo')
            ->getProcess();

        self::assertStringContainsString('test_foo', $process->command());
    }

    public function test_that_option_without_value_does_not_append_extra_token(): void
    {
        $process = ProcessBuilder::create('phpunit')
            ->option('no-coverage')
            ->getProcess();

        self::assertStringContainsString('--no-coverage', $process->command());
        // Two tokens total: the executable and the flag
        self::assertSame(2, count(explode(' ', $process->command())));
    }

    public function test_that_option_ignores_empty_string(): void
    {
        $builder = ProcessBuilder::create('echo');
        $result = $builder->option('');

        self::assertSame($builder, $result);
    }

    public function test_that_short_adds_single_dash_when_absent(): void
    {
        $process = ProcessBuilder::create('php')
            ->short('v')
            ->getProcess();

        self::assertStringContainsString('-v', $process->command());
    }

    public function test_that_short_preserves_existing_dash_prefix(): void
    {
        $process = ProcessBuilder::create('php')
            ->short('-v')
            ->getProcess();

        self::assertStringContainsString('-v', $process->command());
    }

    public function test_that_short_with_value_appends_value(): void
    {
        $process = ProcessBuilder::create('php')
            ->short('d', 'memory_limit=512M')
            ->getProcess();

        self::assertStringContainsString('memory_limit=512M', $process->command());
    }

    public function test_that_short_ignores_empty_string(): void
    {
        $builder = ProcessBuilder::create('echo');
        $result = $builder->short('');

        self::assertSame($builder, $result);
    }

    public function test_that_clear_args_removes_all_positional_arguments(): void
    {
        $process = ProcessBuilder::create()
            ->prefix('echo')
            ->arg('hello')
            ->arg('world')
            ->clearArgs()
            ->getProcess();

        self::assertStringNotContainsString('hello', $process->command());
        self::assertStringNotContainsString('world', $process->command());
    }

    public function test_that_directory_sets_working_directory(): void
    {
        $process = ProcessBuilder::create('echo')
            ->directory('/var/www')
            ->getProcess();

        self::assertSame('/var/www', $process->directory());
    }

    public function test_that_directory_can_be_set_to_null(): void
    {
        $process = ProcessBuilder::create('echo')
            ->directory(null)
            ->getProcess();

        self::assertNull($process->directory());
    }

    public function test_that_input_accepts_string(): void
    {
        $process = ProcessBuilder::create('cat')
            ->input('hello')
            ->getProcess();

        self::assertSame('hello', $process->input());
    }

    public function test_that_input_casts_scalar_to_string(): void
    {
        $process = ProcessBuilder::create('cat')
            ->input(42)
            ->getProcess();

        self::assertSame('42', $process->input());
    }

    public function test_that_input_accepts_resource(): void
    {
        $resource = fopen('php://memory', 'r');

        $process = ProcessBuilder::create('cat')
            ->input($resource)
            ->getProcess();

        self::assertSame($resource, $process->input());

        fclose($resource);
    }

    public function test_that_input_can_be_set_to_null(): void
    {
        $process = ProcessBuilder::create('cat')
            ->input(null)
            ->getProcess();

        self::assertNull($process->input());
    }

    public function test_that_input_ignores_non_scalar_non_resource_values(): void
    {
        $process = ProcessBuilder::create('cat')
            ->input([])
            ->getProcess();

        self::assertNull($process->input());
    }

    public function test_that_timeout_sets_value_in_seconds(): void
    {
        $process = ProcessBuilder::create('echo')
            ->timeout(120)
            ->getProcess();

        self::assertSame(120.0, $process->timeout());
    }

    public function test_that_timeout_can_be_set_to_null(): void
    {
        $process = ProcessBuilder::create('echo')
            ->timeout(null)
            ->getProcess();

        self::assertNull($process->timeout());
    }

    public function test_that_timeout_throws_on_negative_value(): void
    {
        self::expectException(DomainException::class);

        ProcessBuilder::create('echo')->timeout(-1);
    }

    public function test_that_default_timeout_is_sixty_seconds(): void
    {
        $process = ProcessBuilder::create('echo')->getProcess();

        self::assertSame(60.0, $process->timeout());
    }

    public function test_that_set_env_adds_environment_variable(): void
    {
        $process = ProcessBuilder::create('echo')
            ->setEnv('APP_ENV', 'prod')
            ->setEnv('APP_DEBUG', 'false')
            ->getProcess();

        self::assertSame(['APP_ENV' => 'prod', 'APP_DEBUG' => 'false'], $process->environment());
    }

    public function test_that_environment_is_null_when_no_vars_are_set(): void
    {
        $process = ProcessBuilder::create('echo')->getProcess();

        self::assertNull($process->environment());
    }

    public function test_that_stdout_sets_callback(): void
    {
        $callback = static function (string $data): void {};

        $process = ProcessBuilder::create('echo')
            ->stdout($callback)
            ->getProcess();

        self::assertSame($callback, $process->stdout());
    }

    public function test_that_stdout_can_be_set_to_null(): void
    {
        $process = ProcessBuilder::create('echo')
            ->stdout(null)
            ->getProcess();

        self::assertNull($process->stdout());
    }

    public function test_that_stderr_sets_callback(): void
    {
        $callback = static function (string $data): void {};

        $process = ProcessBuilder::create('echo')
            ->stderr($callback)
            ->getProcess();

        self::assertSame($callback, $process->stderr());
    }

    public function test_that_stderr_can_be_set_to_null(): void
    {
        $process = ProcessBuilder::create('echo')
            ->stderr(null)
            ->getProcess();

        self::assertNull($process->stderr());
    }

    public function test_that_disable_output_sets_flag(): void
    {
        $process = ProcessBuilder::create('echo')
            ->disableOutput()
            ->getProcess();

        self::assertTrue($process->isOutputDisabled());
    }

    public function test_that_enable_output_clears_flag(): void
    {
        $process = ProcessBuilder::create('echo')
            ->disableOutput()
            ->enableOutput()
            ->getProcess();

        self::assertFalse($process->isOutputDisabled());
    }

    public function test_that_get_process_throws_when_no_prefix_or_arguments_set(): void
    {
        self::expectException(MethodCallException::class);

        (new ProcessBuilder())->getProcess();
    }

    public function test_that_get_process_shell_escapes_arguments(): void
    {
        $process = ProcessBuilder::create(['echo', 'hello world'])
            ->getProcess();

        self::assertStringContainsString("'hello world'", $process->command());
    }

    public function test_that_builder_methods_return_same_instance(): void
    {
        $builder = ProcessBuilder::create();

        self::assertSame($builder, $builder->prefix('php'));
        self::assertSame($builder, $builder->arg('test.php'));
        self::assertSame($builder, $builder->option('filter'));
        self::assertSame($builder, $builder->short('v'));
        self::assertSame($builder, $builder->clearArgs());
        self::assertSame($builder, $builder->directory('/tmp'));
        self::assertSame($builder, $builder->input('test'));
        self::assertSame($builder, $builder->timeout(30));
        self::assertSame($builder, $builder->setEnv('FOO', 'bar'));
        self::assertSame($builder, $builder->stdout(null));
        self::assertSame($builder, $builder->stderr(null));
        self::assertSame($builder, $builder->disableOutput());
        self::assertSame($builder, $builder->enableOutput());
    }
}
