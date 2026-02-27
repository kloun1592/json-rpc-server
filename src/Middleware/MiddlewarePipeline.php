<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Middleware;

use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Protocol\Request;

/**
 * Executes middleware chain around core request handler.
 */
final class MiddlewarePipeline implements RpcRequestHandlerInterface
{
    /**
     * @var list<RpcMiddlewareInterface>
     */
    private array $middlewares;

    /**
     * @param iterable<RpcMiddlewareInterface> $middlewares
     */
    public function __construct(
        private readonly RpcRequestHandlerInterface $coreHandler,
        iterable $middlewares = [],
    ) {
        $this->middlewares = $this->normalizeMiddlewares($middlewares);
    }

    public function handle(Request $request): mixed
    {
        $handler = $this->buildHandlerChain();
        return $handler->handle($request);
    }

    /**
     * @param iterable<RpcMiddlewareInterface> $middlewares
     * @return list<RpcMiddlewareInterface>
     */
    private function normalizeMiddlewares(iterable $middlewares): array
    {
        if (is_array($middlewares)) {
            return array_values($middlewares);
        }

        return array_values(iterator_to_array($middlewares, false));
    }

    private function buildHandlerChain(): RpcRequestHandlerInterface
    {
        $handler = $this->coreHandler;

        // Build from tail to head so middleware keeps declaration order.
        for ($i = count($this->middlewares) - 1; $i >= 0; --$i) {
            $handler = new DelegatingRequestHandler($this->middlewares[$i], $handler);
        }

        return $handler;
    }
}
