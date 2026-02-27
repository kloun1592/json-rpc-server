<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Middleware;

use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\Protocol\Request;

/**
 * Adapter that bridges Request object to method dispatcher.
 */
final readonly class DispatcherRequestHandler implements RpcRequestHandlerInterface
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    public function handle(Request $request): mixed
    {
        return $this->dispatcher->dispatch($request->method, $request->params);
    }
}
