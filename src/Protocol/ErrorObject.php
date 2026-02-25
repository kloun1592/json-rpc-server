<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Protocol;

/**
 * Error payload embedded into JSON-RPC response.
 */
final readonly class ErrorObject
{
    public function __construct(
        public int $code,
        public string $message,
        public mixed $data = null,
    ) {
    }

    /**
     * Convert to protocol shape.
     *
     * `data` is optional and should be omitted when not provided.
     *
     * @return array{code:int,message:string,data?:mixed}
     */
    public function toArray(): array
    {
        $payload = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        return $payload;
    }
}
