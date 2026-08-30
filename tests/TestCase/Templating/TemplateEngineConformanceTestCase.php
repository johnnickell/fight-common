<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Templating;

use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Fight\Test\Common\TestCase\UnitTestCase;

/**
 * Defines common observable template-engine behavior for framework adapters.
 */
abstract class TemplateEngineConformanceTestCase extends UnitTestCase
{
    private string $templatesDirectory;
    private string $outsideTemplate;

    /**
     * Creates the configured template engine.
     */
    abstract protected function templateEngine(string $templatesDirectory): TemplateEngine;

    /**
     * Returns the adapter's supported template extension.
     */
    abstract protected function templateExtension(): string;

    /**
     * Returns a template that renders the supplied name.
     */
    abstract protected function renderingTemplate(): string;

    /**
     * Returns a template that renders the registered helper name.
     */
    abstract protected function helperTemplate(): string;

    /**
     * Returns a template that raises a native rendering failure.
     */
    abstract protected function failingTemplate(): string;

    /**
     * Returns the expected native rendering failure message.
     */
    abstract protected function nativeFailureMessage(): string;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->templatesDirectory = sys_get_temp_dir().'/template-conformance-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->templatesDirectory));
        $this->outsideTemplate = $this->templatesDirectory.'-outside.'.$this->templateExtension();
        self::assertIsInt(file_put_contents($this->outsideTemplate, $this->renderingTemplate()));
        $this->writeTemplate('render', $this->renderingTemplate());
        $this->writeTemplate('helper', $this->helperTemplate());
        $this->writeTemplate('failure', $this->failingTemplate());
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        self::assertTrue(unlink($this->outsideTemplate));
        $this->removeTemporaryDirectory($this->templatesDirectory, 'template-conformance-');

        parent::tearDown();
    }

    public function test_that_render_preserves_template_data(): void
    {
        self::assertSame(
            'Hello Ada',
            trim($this->engine()->render($this->template('render'), ['name' => 'Ada']))
        );
    }

    public function test_that_exists_reports_present_and_missing_templates(): void
    {
        $engine = $this->engine();

        self::assertTrue($engine->exists($this->template('render')));
        self::assertFalse($engine->exists($this->template('missing')));
    }

    public function test_that_supports_only_the_native_template_extension(): void
    {
        $engine = $this->engine();

        self::assertTrue($engine->supports($this->template('render')));
        self::assertFalse($engine->supports('render.unsupported'));
    }

    public function test_that_registered_helpers_are_visible_to_templates(): void
    {
        $helper = $this->helper('fight_helper');
        $engine = $this->engine();

        $engine->addHelper($helper);

        self::assertTrue($engine->hasHelper($helper));
        self::assertSame('fight_helper', trim($engine->render($this->template('helper'))));
    }

    public function test_that_duplicate_helper_names_are_rejected(): void
    {
        $engine = $this->engine();
        $engine->addHelper($this->helper('duplicate'));

        $this->expectException(DuplicateHelperException::class);

        $engine->addHelper($this->helper('duplicate'));
    }

    public function test_that_helpers_remain_scoped_to_the_engine_that_registered_them(): void
    {
        $registered = $this->engine();
        $isolated = $this->engine();
        $helper = $this->helper('isolated');
        $registered->addHelper($helper);

        self::assertTrue($registered->hasHelper($helper));
        self::assertFalse($isolated->hasHelper($helper));
    }

    public function test_that_missing_templates_have_their_name_preserved(): void
    {
        $template = $this->template('missing');

        try {
            $this->engine()->render($template);
            self::fail('Expected the missing template to be translated.');
        } catch (TemplateNotFoundException $exception) {
            self::assertSame($template, $exception->getTemplate());
        }
    }

    public function test_that_templates_outside_the_application_selected_root_are_rejected(): void
    {
        $template = '../'.basename($this->outsideTemplate);
        $engine = $this->engine();

        self::assertFalse($engine->exists($template));
        $this->expectException(TemplateNotFoundException::class);

        $engine->render($template);
    }

    public function test_that_native_rendering_failures_are_translated_without_losing_the_cause(): void
    {
        try {
            $this->engine()->render($this->template('failure'));
            self::fail('Expected the native rendering failure to be translated.');
        } catch (TemplatingException $exception) {
            self::assertStringContainsString($this->nativeFailureMessage(), $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
            self::assertStringContainsString(
                $this->nativeFailureMessage(),
                $exception->getPrevious()->getMessage()
            );
        }
    }

    private function engine(): TemplateEngine
    {
        return $this->templateEngine($this->templatesDirectory);
    }

    private function template(string $name): string
    {
        return $name.'.'.$this->templateExtension();
    }

    private function writeTemplate(string $name, string $contents): void
    {
        self::assertIsInt(file_put_contents($this->templatesDirectory.'/'.$this->template($name), $contents));
    }

    private function helper(string $name): TemplateHelper
    {
        return new class ($name) implements TemplateHelper {
            public function __construct(private readonly string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }
        };
    }
}
