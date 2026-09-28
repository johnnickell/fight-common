<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Closure;
use Fight\Common\Application\Mcp\McpDiagnostics;
use Fight\Common\Application\Mcp\McpJsonResponse;
use Fight\Common\Application\Mcp\McpProgressReporter;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Domain\Exception\DomainException;
use Throwable;

/**
 * Class McpToolExecution
 *
 * @internal Request-scoped semantic delivery pending HTTP adapter proof; not a transport API
 */
final class McpToolExecution implements McpProgressReporter
{
    private bool $started = false;
    private bool $invoked = false;
    private bool $completed = false;
    private bool $cancelled = false;
    private bool $delivering = false;
    private bool $diagnosed = false;
    private ?float $previous = null;
    private ?Throwable $failure = null;

    /**
     * Constructs McpToolExecution
     *
     * Delivery is synchronous and ordered. It must not defer messages or retain them for later emission.
     * The outer adapter marks cancellation before returning when it observes client closure.
     *
     * @param McpRequest $request
     * @param Closure $deliver
     * @param McpDiagnostics|null $diagnostics
     *
     * @phpstan-param Closure(array<string, mixed>): void $deliver
     */
    public function __construct(
        private readonly McpRequest $request,
        private readonly Closure $deliver,
        private readonly ?McpDiagnostics $diagnostics = null
    ) {
        if ($request->method() !== 'tools/call') {
            throw new DomainException('A Tool execution requires a tools/call request.');
        }
    }

    /**
     * Returns whether this execution owns the exact decoded request
     */
    public function accepts(McpRequest $request): bool
    {
        return $request === $this->request;
    }

    /**
     * Returns the reporter for exactly one invocation during this execution
     */
    public function reporterFor(McpRequest $request): McpProgressReporter
    {
        if (!$this->accepts($request) || !$this->started || $this->completed || $this->invoked || $this->cancelled) {
            throw new DomainException('A Tool execution cannot be shared or invoked outside its request lifecycle.');
        }

        $this->invoked = true;

        return $request->metadata()->progressToken() === null ? new NullMcpProgressReporter() : $this;
    }

    /**
     * Executes one guarded decoded request through the semantic responder and completes delivery once
     *
     * This is the central diagnostic boundary; dispatch itself never logs. Terminal delivery failure
     * propagates without retry because the outer adapter may already have written the final message.
     */
    public function run(McpResponder $responder): void
    {
        if ($this->started) {
            throw new DomainException('A Tool execution can only run once.');
        }

        $this->started = true;
        if ($this->cancelled) {
            return;
        }

        try {
            $response = $responder->dispatch($this->request);
            if ($this->failure !== null) {
                throw $this->failure;
            }

            // Complete response preparation before the terminal delivery boundary.
            $response->toJson();
        } catch (McpProtocolException $exception) {
            $response = McpJsonResponse::error($this->request->id(), $exception->protocolError());
        } catch (Throwable $failure) {
            $this->diagnose($failure);
            $response = McpJsonResponse::error($this->request->id(), McpProtocolError::internalError());
        }

        $this->completed = true;
        if ($this->cancelled) {
            return;
        }

        try {
            ($this->deliver)($response->toArray());
        } catch (Throwable $throwable) {
            $this->diagnose($throwable);

            throw $throwable;
        }
    }

    /**
     * Sets advisory cancellation without rolling back or interrupting application work
     */
    public function cancel(): void
    {
        if (!$this->completed) {
            $this->cancelled = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function report(float $progress, ?float $total = null, ?string $message = null): void
    {
        if ($this->cancelled) {
            return;
        }

        try {
            $this->validateReport($progress, $total, $message);
            $parameters = ['progressToken' => $this->request->metadata()->progressToken(), 'progress' => $progress];
            if ($total !== null) {
                $parameters['total'] = $total;
            }

            if ($message !== null) {
                $parameters['message'] = $message;
            }

            $this->previous = $progress;
            $this->delivering = true;
            ($this->deliver)(['jsonrpc' => '2.0', 'method' => 'notifications/progress', 'params' => $parameters]);
        } catch (Throwable $throwable) {
            // Retain violations even when Tool code catches them or maps their exception class.
            $this->failure ??= new DomainException('Tool progress failed.', 0, $throwable);

            throw $this->failure;
        } finally {
            $this->delivering = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * Validates an open invocation and strictly increasing finite work without result content
     */
    private function validateReport(float $progress, ?float $total, ?string $message): void
    {
        if (
            !$this->invoked || $this->completed || $this->delivering || $this->failure !== null
            || $this->request->metadata()->progressToken() === null
            || !is_finite($progress) || $progress < 0 || ($this->previous !== null && $progress <= $this->previous)
            || ($total !== null && (!is_finite($total) || $total < $progress))
            || ($message !== null && preg_match('//u', $message) !== 1)
        ) {
            throw new DomainException('Tool progress requires an open request and strictly increasing finite status.');
        }
    }

    /**
     * Records one unexpected failure without exposing a diagnostic sink failure
     */
    private function diagnose(Throwable $failure): void
    {
        if ($this->diagnosed) {
            return;
        }

        $this->diagnosed = true;
        try {
            $this->diagnostics?->record($failure);
        } catch (Throwable) {
            // Diagnostics cannot become client-visible failures.
        }
    }
}
