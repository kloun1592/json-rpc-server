<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Exception;

use CatalogCodex\JsonRpc\Protocol\ErrorCode;

/**
 * Convenience exception for unexpected internal failures.
 */
final class InternalErrorException extends JsonRpcException
{
    public function __construct(
        string $message = 'Internal error',
        mixed $data = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(ErrorCode::INTERNAL_ERROR, $message, $data, $previous);
    }
}
