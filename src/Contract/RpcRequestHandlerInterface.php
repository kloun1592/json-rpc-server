<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Contract;

use CatalogCodex\JsonRpc\Protocol\Request;

/**
 * Minimal handler abstraction used by middleware pipeline.
 */
interface RpcRequestHandlerInterface
{
    /**
     * Handle a validated JSON-RPC request object.
     */
    public function handle(Request $request): mixed;
}
