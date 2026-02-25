<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc;

use CatalogCodex\JsonRpc\Exception\InvalidParamsException;
use CatalogCodex\JsonRpc\Support\CallableInvoker;

/**
 * Resolves method handler from registry and invokes it with normalized params.
 */
final class Dispatcher
{
    public function __construct(
        private readonly MethodRegistry $registry,
        private readonly CallableInvoker $invoker = new CallableInvoker(),
    ) {
    }

    /**
     * @throws InvalidParamsException
     */
    public function dispatch(string $method, mixed $params): mixed
    {
        $handler = $this->registry->get($method);

        try {
            return $this->invoker->invoke($handler, $params);
        } catch (\TypeError $e) {
            // Native type errors are exposed as JSON-RPC invalid params.
            throw new InvalidParamsException('Parameter type mismatch', ['reason' => $e->getMessage()], $e);
        } catch (\ArgumentCountError $e) {
            throw new InvalidParamsException('Argument count mismatch', ['reason' => $e->getMessage()], $e);
        }
    }
}
