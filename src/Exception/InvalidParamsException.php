<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Exception;

use CatalogCodex\JsonRpc\Protocol\ErrorCode;

/**
 * Raised when method arguments do not match handler signature or constraints.
 */
final class InvalidParamsException extends JsonRpcException
{
    public function __construct(
        string $message = 'Invalid method parameter(s)',
        mixed $data = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(ErrorCode::INVALID_PARAMS, $message, $data, $previous);
    }
}
