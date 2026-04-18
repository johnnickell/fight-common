<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Templating;

use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Throwable;

/**
 * Class PhpEngine
 */
final class PhpEngine implements TemplateEngine
{
    protected array $helpers = [];
    protected array $cache = [];
    protected array $parents = [];
    protected array $stack = [];
    protected array $blocks = [];
    protected array $openBlocks = [];
    protected string $current;

    /**
     * Constructs PhpEngine
     *
     * @throws Throwable
     */
    public function __construct(protected array $paths, array $helpers = [])
    {
        foreach ($helpers as $helper) {
            $this->addHelper($helper);
        }
    }

    /**
     * Retrieves a helper
     *
     * @throws TemplatingException When the helper is not defined
     */
    public function get(string $name): TemplateHelper
    {
        if (!isset($this->helpers[$name])) {
            $message = sprintf('Template helper "%s" is not defined', $name);
            throw new TemplatingException($message);
        }

        return $this->helpers[$name];
    }

    /**
     * Checks if a helper is defined
     */
    public function has(string $name): bool
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Escapes HTML content
     */
    public function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }

    /**
     * Extends the current template
     */
    public function extends(string $template): void
    {
        $this->parents[$this->current] = $template;
    }

    /**
     * @inheritDoc
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->loadTemplate($template);
        $key = hash('sha256', $file);
        $this->current = $key;
        $this->parents[$key] = null;

        $content = $this->evaluate($file, $data);

        if (is_string($this->parents[$key])) {
            $content = $this->render($this->parents[$key], $data);
        }

        return $content;
    }

    /**
     * Starts a block
     *
     * @throws TemplatingException When the block is already started
     */
    public function startBlock(string $name): void
    {
        if (in_array($name, $this->openBlocks)) {
            $message = sprintf('Block "%s" is already started', $name);
            throw new TemplatingException($message);
        }

        $this->openBlocks[] = $name;
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = '';
        }

        ob_start();
        ob_implicit_flush(false);
    }

    /**
     * Ends a block
     *
     * @throws TemplatingException When there is no block started
     */
    public function endBlock(): void
    {
        if (!$this->openBlocks) {
            throw new TemplatingException('No block started');
        }

        $name = array_pop($this->openBlocks);

        $content = ob_get_clean();

        if (empty($this->blocks[$name])) {
            $this->blocks[$name] = $content;
        }

        $this->outputContent($name);
    }

    /**
     * Checks if a block exists
     */
    public function hasBlock(string $name): bool
    {
        return isset($this->blocks[$name]);
    }

    /**
     * Sets block content
     */
    public function setContent(string $name, string $content): void
    {
        $this->blocks[$name] = $content;
    }

    /**
     * Retrieves block content
     */
    public function getContent(string $name, ?string $default = null): ?string
    {
        if (!isset($this->blocks[$name])) {
            return $default;
        }

        return $this->blocks[$name];
    }

    /**
     * @inheritDoc
     */
    public function exists(string $template): bool
    {
        foreach ($this->paths as $path) {
            $template = str_replace(':', DIRECTORY_SEPARATOR, $template);
            $file = $path.DIRECTORY_SEPARATOR.$template;
            if (is_file($file) && is_readable($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function supports(string $template): bool
    {
        return pathinfo($template, PATHINFO_EXTENSION) === 'php';
    }

    /**
     * @inheritDoc
     */
    public function addHelper(TemplateHelper $helper): void
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            throw DuplicateHelperException::fromName($name);
        }

        $this->helpers[$name] = $helper;
    }

    /**
     * @inheritDoc
     */
    public function hasHelper(TemplateHelper $helper): bool
    {
        $name = $helper->getName();

        if (isset($this->helpers[$name])) {
            return true;
        }

        return false;
    }

    /**
     * Outputs a block
     */
    public function outputContent(string $name, ?string $default = null): bool
    {
        if (!isset($this->blocks[$name])) {
            if ($default !== null) {
                echo $default;

                return true;
            }

            return false;
        }

        echo $this->blocks[$name];

        return true;
    }

    /**
     * Evaluates a PHP template
     *
     * @throws TemplatingException When data is not valid
     */
    private function evaluate(string $file, array $data = []): string
    {
        $evalFile = $file;
        $evalData = $data;
        unset($file, $data);

        if (isset($evalData['this'])) {
            throw new TemplatingException('Invalid data key: this');
        }

        extract($evalData, EXTR_SKIP);
        $evalData = null;

        ob_start();
        require $evalFile;
        $evalFile = null;

        return ob_get_clean();
    }

    /**
     * Loads the given template
     *
     * @throws TemplateNotFoundException
     */
    private function loadTemplate(string $template): string
    {
        if (!isset($this->cache[$template])) {
            $file = $this->getTemplatePath($template);
            $this->cache[$template] = $file;
        }

        return $this->cache[$template];
    }

    /**
     * Retrieves the absolute path to the template
     *
     * @throws TemplateNotFoundException When the template is not found
     */
    private function getTemplatePath(string $template): string
    {
        foreach ($this->paths as $path) {
            $template = str_replace(':', DIRECTORY_SEPARATOR, $template);
            $file = $path.DIRECTORY_SEPARATOR.$template;
            if (is_file($file) && is_readable($file)) {
                return $file;
            }
        }
        throw TemplateNotFoundException::fromName($template);
    }
}
