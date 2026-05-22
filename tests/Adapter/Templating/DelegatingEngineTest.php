<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating;

use Fight\Common\Adapter\Templating\DelegatingEngine;
use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DelegatingEngine::class)]
class DelegatingEngineTest extends UnitTestCase
{
    public function test_that_render_delegates_to_supporting_engine(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('page.php')->andReturn(true);
        $engine->shouldReceive('render')->once()->with('page.php', ['key' => 'val'])->andReturn('rendered');
        $engine->shouldReceive('hasHelper')->andReturn(true);

        $delegating = new DelegatingEngine([$engine]);

        self::assertSame('rendered', $delegating->render('page.php', ['key' => 'val']));
    }

    public function test_that_render_propagates_helpers_before_render(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'my_helper'; }
        };

        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('page.php')->andReturn(true);
        $engine->shouldReceive('hasHelper')->once()->with($helper)->andReturn(false);
        $engine->shouldReceive('addHelper')->once()->with($helper);
        $engine->shouldReceive('render')->once()->with('page.php', [])->andReturn('rendered');

        $delegating = new DelegatingEngine([$engine]);
        $delegating->addHelper($helper);

        self::assertSame('rendered', $delegating->render('page.php'));
    }

    public function test_that_render_skips_helper_propagation_when_engine_already_has_helper(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'my_helper'; }
        };

        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('page.php')->andReturn(true);
        $engine->shouldReceive('hasHelper')->once()->with($helper)->andReturn(true);
        $engine->shouldReceive('render')->once()->with('page.php', [])->andReturn('rendered');

        $delegating = new DelegatingEngine([$engine]);
        $delegating->addHelper($helper);

        $delegating->render('page.php');
    }

    public function test_that_render_throws_templating_exception_when_no_engine_supports(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('unknown.xyz')->andReturn(false);

        $delegating = new DelegatingEngine([$engine]);

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('No template engines loaded to support template: unknown.xyz');

        $delegating->render('unknown.xyz');
    }

    public function test_that_exists_returns_false_when_template_not_supported(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('unknown.xyz')->andReturn(false);

        $delegating = new DelegatingEngine([$engine]);

        self::assertFalse($delegating->exists('unknown.xyz'));
    }

    public function test_that_exists_delegates_to_engine(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->with('page.php')->andReturn(true);
        $engine->shouldReceive('exists')->once()->with('page.php')->andReturn(true);

        $delegating = new DelegatingEngine([$engine]);

        self::assertTrue($delegating->exists('page.php'));
    }

    public function test_that_supports_returns_true_when_any_engine_supports(): void
    {
        $engine1 = $this->mock(TemplateEngine::class);
        $engine1->shouldReceive('supports')->once()->with('page.php')->andReturn(false);
        $engine2 = $this->mock(TemplateEngine::class);
        $engine2->shouldReceive('supports')->once()->with('page.php')->andReturn(true);

        $delegating = new DelegatingEngine([$engine1, $engine2]);

        self::assertTrue($delegating->supports('page.php'));
    }

    public function test_that_supports_returns_false_when_no_engine_supports(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('page.php')->andReturn(false);

        $delegating = new DelegatingEngine([$engine]);

        self::assertFalse($delegating->supports('page.php'));
    }

    public function test_that_add_helper_stores_helper(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'test_helper'; }
        };

        $delegating = new DelegatingEngine();

        $delegating->addHelper($helper);

        self::assertTrue($delegating->hasHelper($helper));
    }

    public function test_that_add_helper_throws_on_duplicate(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'dup'; }
        };

        $delegating = new DelegatingEngine();
        $delegating->addHelper($helper);

        $this->expectException(DuplicateHelperException::class);

        $delegating->addHelper($helper);
    }

    public function test_that_has_helper_returns_false_for_unknown_helper(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'unknown'; }
        };

        $delegating = new DelegatingEngine();

        self::assertFalse($delegating->hasHelper($helper));
    }

    public function test_that_add_engine_registers_engine(): void
    {
        $engine = $this->mock(TemplateEngine::class);
        $engine->shouldReceive('supports')->once()->with('page.php')->andReturn(true);
        $engine->shouldReceive('hasHelper')->andReturn(true);
        $engine->shouldReceive('render')->once()->andReturn('rendered');

        $delegating = new DelegatingEngine();
        $delegating->addEngine($engine);

        self::assertSame('rendered', $delegating->render('page.php'));
    }

    public function test_that_construct_accepts_no_engines(): void
    {
        $delegating = new DelegatingEngine();

        $this->expectException(TemplatingException::class);

        $delegating->render('page.php');
    }
}
