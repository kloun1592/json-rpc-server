<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Protocol;

/**
 * Canonical JSON-RPC 2.0 error codes.
 *
 * See: https://www.jsonrpc.org/specification#error_object
 */
final class ErrorCode
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    private function __construct()
    {
    }
}
