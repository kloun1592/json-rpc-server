<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Protocol;

/**
 * Internal normalized representation of a JSON-RPC request.
 */
final readonly class Request
{
    public function __construct(
        public string $method,
        public mixed $params,
        public mixed $id,
        public bool $hasId,
    ) {
    }

    /**
     * A request without "id" is a notification and must not yield a response.
     */
    public function isNotification(): bool
    {
        return !$this->hasId;
    }
}
