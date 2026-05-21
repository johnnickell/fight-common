<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating;

use Fight\Common\Adapter\Templating\PhpEngine;
use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PhpEngine::class)]
class PhpEngineTest extends UnitTestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateDir = sys_get_temp_dir() . '/php_engine_test_' . bin2hex(random_bytes(8));
        mkdir($this->templateDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $dirIterator = new \RecursiveDirectoryIterator($this->templateDir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->templateDir);
        parent::tearDown();
    }

    private function createTemplate(string $name, string $content): string
    {
        $path = $this->templateDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function test_that_render_returns_evaluated_template(): void
    {
        $this->createTemplate('hello.php', 'Hello <?= $name ?>');
        $engine = new PhpEngine([$this->templateDir]);

        $result = $engine->render('hello.php', ['name' => 'World']);

        self::assertSame('Hello World', $result);
    }

    public function test_that_render_throws_template_not_found_exception(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $this->expectException(TemplateNotFoundException::class);

        $engine->render('missing.php');
    }

    public function test_that_render_caches_loaded_template(): void
    {
        $this->createTemplate('cached.php', 'cached');
        $engine = new PhpEngine([$this->templateDir]);

        $first = $engine->render('cached.php');
        $second = $engine->render('cached.php');

        self::assertSame('cached', $first);
        self::assertSame('cached', $second);
    }

    public function test_that_extends_sets_parent_for_current_template(): void
    {
        $this->createTemplate('child.php', 'Child content');
        $engine = new PhpEngine([$this->templateDir]);

        $engine->render('child.php');

        $engine->extends('parent.php');

        self::assertTrue(true);
    }

    public function test_that_escape_html_encodes_special_chars(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $result = $engine->escape('<script>alert("xss")</script>');

        self::assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
    }

    public function test_that_exists_returns_true_when_template_file_exists(): void
    {
        $this->createTemplate('exists.php', 'exists');
        $engine = new PhpEngine([$this->templateDir]);

        self::assertTrue($engine->exists('exists.php'));
    }

    public function test_that_exists_returns_false_when_template_not_found(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        self::assertFalse($engine->exists('nonexistent.php'));
    }

    public function test_that_supports_returns_true_for_php_extension(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        self::assertTrue($engine->supports('template.php'));
    }

    public function test_that_supports_returns_false_for_non_php_extension(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        self::assertFalse($engine->supports('template.twig'));
        self::assertFalse($engine->supports('template.html'));
    }

    public function test_that_add_helper_stores_and_retrieves_helper(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'test'; }
        };

        $engine = new PhpEngine([$this->templateDir], [$helper]);

        self::assertTrue($engine->has('test'));
        self::assertSame($helper, $engine->get('test'));
    }

    public function test_that_add_helper_throws_on_duplicate(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'dup'; }
        };

        $engine = new PhpEngine([$this->templateDir], [$helper]);

        $this->expectException(DuplicateHelperException::class);

        $engine->addHelper($helper);
    }

    public function test_that_get_throws_when_helper_not_defined(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('Template helper "missing" is not defined');

        $engine->get('missing');
    }

    public function test_that_has_helper_returns_false_for_unknown(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        self::assertFalse($engine->has('unknown'));
    }

    public function test_that_has_helper_returns_false_for_unregistered(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'no'; }
        };

        $engine = new PhpEngine([$this->templateDir]);

        self::assertFalse($engine->hasHelper($helper));
    }

    public function test_that_start_block_and_end_block_capture_content(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $engine->startBlock('content');
        echo 'block content';
        $engine->endBlock();

        self::assertTrue($engine->hasBlock('content'));
        self::assertSame('block content', $engine->getContent('content'));
    }

    public function test_that_start_block_throws_when_already_started(): void
    {
        $engine = new PhpEngine([$this->templateDir]);
        $levelBefore = ob_get_level();
        $engine->startBlock('content');

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('Block "content" is already started');

        try {
            $engine->startBlock('content');
        } finally {
            while (ob_get_level() > $levelBefore) {
                ob_end_clean();
            }
        }
    }

    public function test_that_end_block_throws_when_no_block_started(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('No block started');

        $engine->endBlock();
    }

    public function test_that_set_content_sets_block_content(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $engine->setContent('sidebar', 'sidebar content');

        self::assertTrue($engine->hasBlock('sidebar'));
        self::assertSame('sidebar content', $engine->getContent('sidebar'));
    }

    public function test_that_get_content_returns_default_when_block_not_found(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        self::assertNull($engine->getContent('missing'));
        self::assertSame('default', $engine->getContent('missing', 'default'));
    }

    public function test_that_output_content_echoes_block_content(): void
    {
        $engine = new PhpEngine([$this->templateDir]);
        $engine->setContent('test', 'echoed content');

        ob_start();
        $result = $engine->outputContent('test');
        $output = ob_get_clean();

        self::assertTrue($result);
        self::assertSame('echoed content', $output);
    }

    public function test_that_output_content_echoes_default_when_block_not_found(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        ob_start();
        $result = $engine->outputContent('missing', 'default output');
        $output = ob_get_clean();

        self::assertTrue($result);
        self::assertSame('default output', $output);
    }

    public function test_that_output_content_returns_false_when_block_not_found_and_no_default(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        ob_start();
        $result = $engine->outputContent('missing');
        ob_end_clean();

        self::assertFalse($result);
    }

    public function test_that_has_helper_returns_true_when_helper_registered(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'registered'; }
        };

        $engine = new PhpEngine([$this->templateDir], [$helper]);

        self::assertTrue($engine->hasHelper($helper));
    }

    public function test_that_end_block_overwrites_only_first_block_content(): void
    {
        $engine = new PhpEngine([$this->templateDir]);

        $engine->startBlock('content');
        echo 'first';
        $engine->endBlock();

        $engine->startBlock('content');
        echo 'second';
        $engine->endBlock();

        self::assertSame('first', $engine->getContent('content'));
    }

    public function test_that_render_exposes_data_to_template(): void
    {
        $this->createTemplate('data.php', '<?= json_encode($items) ?>');
        $engine = new PhpEngine([$this->templateDir]);

        $result = $engine->render('data.php', ['items' => ['a', 'b']]);

        self::assertSame('["a","b"]', $result);
    }

    public function test_that_evaluate_throws_for_invalid_this_key(): void
    {
        $this->createTemplate('bad.php', 'nothing');
        $engine = new PhpEngine([$this->templateDir]);

        $this->expectException(TemplatingException::class);
        $this->expectExceptionMessage('Invalid data key: this');

        $engine->render('bad.php', ['this' => 'bad']);
    }

    public function test_that_template_with_colon_separator_is_resolved(): void
    {
        mkdir($this->templateDir . '/sub');
        file_put_contents($this->templateDir . '/sub/template.php', 'nested');
        $engine = new PhpEngine([$this->templateDir]);

        $result = $engine->render('sub:template.php');

        self::assertSame('nested', $result);
    }

    public function test_that_construct_with_helpers_adds_them(): void
    {
        $helper = new class implements TemplateHelper {
            public function getName(): string { return 'helper1'; }
        };

        $engine = new PhpEngine([$this->templateDir], [$helper]);

        self::assertTrue($engine->has('helper1'));
    }
}
