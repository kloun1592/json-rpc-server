<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Middleware;

use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Protocol\Request;

/**
 * Small immutable wrapper that delegates handling to middleware and next handler.
 */
final readonly class DelegatingRequestHandler implements RpcRequestHandlerInterface
{
    public function __construct(
        private RpcMiddlewareInterface $middleware,
        private RpcRequestHandlerInterface $next,
    ) {
    }

    public function handle(Request $request): mixed
    {
        return $this->middleware->process($request, $this->next);
    }
}
