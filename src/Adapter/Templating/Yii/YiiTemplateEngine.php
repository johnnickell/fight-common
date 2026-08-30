<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Templating\Yii;

use Fight\Common\Application\Templating\Exception\DuplicateHelperException;
use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Common\Application\Templating\TemplateEngine;
use Fight\Common\Application\Templating\TemplateHelper;
use Throwable;
use Yiisoft\View\ViewInterface;

/**
 * Class YiiTemplateEngine
 */
final class YiiTemplateEngine implements TemplateEngine
{
    /** @var array<string, TemplateHelper> */
    private array $helpers = [];

    /**
     * Constructs YiiTemplateEngine
     */
    public function __construct(
        private readonly ViewInterface $view,
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
            $view = $this->view->withClearedState()->withBasePath($this->templatesPath);
            $view->setParameters($this->helpers);

            return $view->render($template, $data);
        } catch (Throwable $throwable) {
            throw new TemplatingException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $template): bool
    {
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
        return isset($this->helpers[$helper->getName()]);
    }
}
