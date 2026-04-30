<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpKernel;

use Fight\Common\Application\HttpFoundation\HttpMethod;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Common\Domain\Value\Basic\StringObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Class JsonRequestMiddleware
 */
final class JsonRequestMiddleware implements HttpKernelInterface, TerminableInterface
{
    /**
     * Constructs JsonRequestMiddleware
     */
    public function __construct(protected HttpKernelInterface $kernel)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        $stateChangeMethods = [
            HttpMethod::POST,
            HttpMethod::PUT,
            HttpMethod::PATCH,
            HttpMethod::DELETE
        ];

        $contentType = StringObject::create($request->headers->get('Content-Type', ''));

        if (in_array($request->getMethod(), $stateChangeMethods) && $contentType->startsWith('application/json')) {
            $data = JsonObject::fromString($request->getContent())->toData();
            $request->request->replace(is_array($data) ? $data : []);
        }

        return $this->kernel->handle($request, $type, $catch);
    }

    /**
     * @inheritDoc
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }
}
