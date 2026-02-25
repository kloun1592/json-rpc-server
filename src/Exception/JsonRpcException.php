<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Exception;

use RuntimeException;

/**
 * Base exception for domain-level errors that should be surfaced to JSON-RPC clients.
 */
class JsonRpcException extends RuntimeException
{
    public function __construct(
        private readonly int $rpcCode,
        string $message,
        private readonly mixed $rpcData = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function rpcCode(): int
    {
        return $this->rpcCode;
    }

    public function rpcData(): mixed
    {
        return $this->rpcData;
    }
}
