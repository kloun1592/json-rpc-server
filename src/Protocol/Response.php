<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Protocol;

/**
 * Internal response model for both success and error outcomes.
 */
final readonly class Response
{
    private function __construct(
        public mixed $id,
        public mixed $result,
        public ?ErrorObject $error,
    ) {
    }

    public static function success(mixed $id, mixed $result): self
    {
        return new self(id: $id, result: $result, error: null);
    }

    public static function error(mixed $id, int $code, string $message, mixed $data = null): self
    {
        return new self(id: $id, result: null, error: new ErrorObject($code, $message, $data));
    }

    /**
     * Convert to JSON-RPC response array.
     *
     * JSON-RPC requires exactly one of `result` or `error`.
     *
     * @return array{jsonrpc:string,id:mixed,result?:mixed,error?:array{code:int,message:string,data?:mixed}}
     */
    public function toArray(): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
        ];

        if ($this->error !== null) {
            $payload['error'] = $this->error->toArray();
            return $payload;
        }

        $payload['result'] = $this->result;
        return $payload;
    }
}
