<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Templating\Laravel;

use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Throwable;

/**
 * Class LaravelBladeTemplateEngine
 *
 * Renders Fight templates through Laravel Blade.
 */
final class LaravelBladeTemplateEngine implements TemplateEngine
{
    /** @var array<string, TemplateHelper> */
    private array $helpers = [];

    /**
     * Constructs LaravelBladeTemplateEngine
     */
    public function __construct(
        private readonly ViewFactory $view,
        private readonly string $templatesPath
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(string $template, array $data = []): string
    {
        if (!$this->exists($template)) {
            throw TemplateNotFoundException::fromName($template);
        }

        try {
            return $this->view->make($this->viewName($template), $data)->render();
        } catch (Throwable $throwable) {
            throw new TemplatingException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $template): bool
    {
        if (!$this->supports($template)) {
            return false;
        }

        $templatesPath = realpath($this->templatesPath);
        $templatePath = realpath($this->templatesPath.'/'.$template);
        $templatesPrefix = rtrim((string) $templatesPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return $templatesPath !== false
            && $templatePath !== false
            && is_file($templatePath)
            && str_starts_with($templatePath, $templatesPrefix);
    }

    /**
     * @inheritDoc
     */
    public function supports(string $template): bool
    {
        return str_ends_with($template, '.blade.php');
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
        $this->view->share($name, $helper);
    }

    /**
     * @inheritDoc
     */
    public function hasHelper(TemplateHelper $helper): bool
    {
        return isset($this->helpers[$helper->getName()]);
    }

    /**
     * Returns the Laravel view name for a verified template
     */
    private function viewName(string $template): string
    {
        $name = substr($template, 0, -strlen('.blade.php'));

        return str_replace(['/', '\\'], '.', $name);
    }
}
