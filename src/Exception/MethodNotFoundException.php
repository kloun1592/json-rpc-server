<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Exception;

use CatalogCodex\JsonRpc\Protocol\ErrorCode;

/**
 * Raised when a method name is not registered in the method registry.
 */
final class MethodNotFoundException extends JsonRpcException
{
    public function __construct(string $method)
    {
        parent::__construct(
            ErrorCode::METHOD_NOT_FOUND,
            sprintf('Method "%s" not found', $method),
        );
    }
}
