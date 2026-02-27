<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Contract;

use CatalogCodex\JsonRpc\Protocol\Request;

/**
 * JSON-RPC middleware contract.
 *
 * Middleware can:
 * - inspect/modify request data,
 * - short-circuit execution,
 * - wrap or transform handler result,
 * - throw domain/protocol exceptions.
 */
interface RpcMiddlewareInterface
{
    public function process(Request $request, RpcRequestHandlerInterface $handler): mixed;
}
