<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\CodeIgniter;

use CodeIgniter\HTTP\Response;
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;
use Psr\Http\Message\ResponseInterface;

/**
 * Class McpResponse
 *
 * Retains a guarded PSR body until CodeIgniter's native send phase instead of storing buffered SSE
 */
final class McpResponse extends Response
{
    private bool $sent = false;

    /**
     * Constructs McpResponse
     */
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly McpResponseEmitter $emitter = new McpResponseEmitter()
    ) {
        parent::__construct(config('App'));
        $this->setStatusCode($response->getStatusCode());
        $this->setProtocolVersion($response->getProtocolVersion());
        foreach ($response->getHeaders() as $name => $values) {
            $this->setHeader($name, $values);
        }
    }

    /**
     * @inheritDoc
     */
    public function sendHeaders(): static
    {
        $this->emitter->prepare($this->response);
        parent::sendHeaders();

        return $this;
    }

    /**
     * Sends the original guarded body once without framework output or toolbar appendages
     */
    public function sendBody(): static
    {
        if (!$this->sent) {
            $this->sent = true;
            $this->emitter->emitBody($this->response);
        }

        return $this;
    }
}
