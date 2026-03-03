# JSON-RPC 2.0 Server Library (PHP 8.3)

[Русская версия](README.ru.md)

A Composer library for building JSON-RPC 2.0 servers in plain PHP with PSR-friendly architecture, tests, and Docker support.

## Features

- JSON-RPC 2.0 protocol compliance:
  - `jsonrpc: "2.0"`
  - single requests
  - batch requests
  - notifications (no response)
  - standard errors: `-32700`, `-32600`, `-32601`, `-32602`, `-32603`
- Method registry + dedicated dispatcher
- Middleware pipeline for cross-cutting concerns (auth, audit, tracing, rate limiting)
- Pluggable batch executor:
  - default sequential strategy
  - optional OpenSwoole coroutine strategy for concurrent batch processing
- Unit + integration tests
- Docker setup for demo server and test runs

## Installation

```bash
composer require mihailromanov/json-rpc-server
```

## Quick Start (Library Usage)

```php
<?php

declare(strict_types=1);

use CatalogCodex\JsonRpc\Batch\SequentialBatchExecutor;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;

$registry = (new MethodRegistry())
    ->register('math.add', static fn (int $a, int $b): int => $a + $b)
    ->register('system.ping', static fn (): string => 'pong');

$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    batchExecutor: new SequentialBatchExecutor(),
);

$responseJson = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":1}');
```

## Concurrent Batch Processing

To process batch requests concurrently, use `OpenSwooleCoroutineBatchExecutor`.

```php
$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    batchExecutor: new \CatalogCodex\JsonRpc\Batch\OpenSwooleCoroutineBatchExecutor(),
);
```

## Middleware Pipeline

Middleware wraps method execution and receives `Request` + `RpcRequestHandlerInterface`.

```php
use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Protocol\Request;

$timingMiddleware = new class () implements RpcMiddlewareInterface {
    public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
    {
        $startedAt = microtime(true);
        $result = $handler->handle($request);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        return [
            'result' => $result,
            'meta' => ['elapsed_ms' => round($elapsedMs, 2)],
        ];
    }
};

$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    middlewares: [$timingMiddleware],
);
```

## Run Demo Server in Docker

```bash
docker compose up --build rpc
```

RPC endpoint: `POST http://localhost:9501/rpc`

### Single request example

```bash
curl -s -X POST http://localhost:9501/rpc \
  -H 'content-type: application/json' \
  -d '{"jsonrpc":"2.0","method":"math.add","params":[10,32],"id":1}'
```

Expected response:

```json
{"jsonrpc":"2.0","id":1,"result":42}
```

### Batch example

```bash
curl -s -X POST http://localhost:9501/rpc \
  -H 'content-type: application/json' \
  -d '[
    {"jsonrpc":"2.0","method":"system.sleep","params":[120],"id":1},
    {"jsonrpc":"2.0","method":"system.sleep","params":[80],"id":2},
    {"jsonrpc":"2.0","method":"system.ping"}
  ]'
```

Notification (`system.ping` without `id`) is not returned in the response.

## Tests

```bash
composer test
```

Or via Docker:

```bash
docker compose run --rm test
```

## Structure

- `src/JsonRpcServer.php` - protocol handling and orchestration
- `src/Dispatcher.php` - method dispatching
- `src/MethodRegistry.php` - method registry
- `src/Support/CallableInvoker.php` - positional/named params to callable mapping
- `src/Contract/RpcMiddlewareInterface.php` - middleware contract
- `src/Contract/RpcRequestHandlerInterface.php` - next-handler contract
- `src/Middleware/*` - middleware pipeline implementation
- `src/Batch/*` - batch execution strategies
- `src/Adapter/OpenSwoole/OpenSwooleHttpServer.php` - OpenSwoole HTTP adapter
- `tests/*` - unit/integration tests
